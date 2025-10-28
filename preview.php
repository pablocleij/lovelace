<?php
// Initialize CMS if needed (ensure snapshot exists)
if(!file_exists('cms/snapshots/latest.json')){
  if(!file_exists('cms/snapshots')){
    mkdir('cms/snapshots', 0777, true);
  }

  // Try to run replay to generate snapshot
  if(file_exists('replay.php')){
    include_once 'replay.php';
  }

  // If still no snapshot, create empty one
  if(!file_exists('cms/snapshots/latest.json')){
    file_put_contents('cms/snapshots/latest.json', json_encode([], JSON_PRETTY_PRINT));
  }
}

// Multi-language support: detect language from query parameter or use default
$langConfig = json_decode(file_get_contents('cms/config/languages.json'), true);
$currentLang = $_GET['lang'] ?? $langConfig['default'];

// Validate language is supported
if(!in_array($currentLang, $langConfig['supported'])){
  $currentLang = $langConfig['default'];
}

// Load theme configuration
$theme = json_decode(file_get_contents('cms/config/theme.json'), true);

// Load snapshot to extract metadata
$snap = json_decode(file_get_contents('cms/snapshots/latest.json'), true);
$pageMeta = null;

// Try to extract metadata from the first page with metadata
foreach($snap as $item){
  if(isset($item['meta'])){
    $pageMeta = $item['meta'];
    break;
  }
}

// Default metadata
$metaTitle = $pageMeta['title'] ?? 'lovelace CMS';
$metaDescription = $pageMeta['description'] ?? 'A conversational AI-powered CMS';
$metaOgImage = $pageMeta['og_image'] ?? '';
$metaOgType = $pageMeta['og_type'] ?? 'website';
$metaKeywords = $pageMeta['keywords'] ?? '';
$metaAuthor = $pageMeta['author'] ?? '';
$metaCanonical = $pageMeta['canonical'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($metaTitle) ?></title>

  <!-- SEO Meta Tags -->
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
  <?php if($metaKeywords): ?>
  <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
  <?php endif; ?>
  <?php if($metaAuthor): ?>
  <meta name="author" content="<?= htmlspecialchars($metaAuthor) ?>">
  <?php endif; ?>
  <?php if($metaCanonical): ?>
  <link rel="canonical" href="<?= htmlspecialchars($metaCanonical) ?>">
  <?php endif; ?>

  <!-- Open Graph / Social Media Meta Tags -->
  <meta property="og:title" content="<?= htmlspecialchars($metaTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta property="og:type" content="<?= htmlspecialchars($metaOgType) ?>">
  <?php if($metaOgImage): ?>
  <meta property="og:image" content="<?= htmlspecialchars($metaOgImage) ?>">
  <?php endif; ?>

  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($metaTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <?php if($metaOgImage): ?>
  <meta name="twitter:image" content="<?= htmlspecialchars($metaOgImage) ?>">
  <?php endif; ?>

  <!-- Live reload script for development -->
  <script src="js/preview-reload.js"></script>
  <style>
    :root {
      --color-primary: <?= $theme['colors']['primary'] ?>;
      --color-secondary: <?= $theme['colors']['secondary'] ?>;
      --font-heading: <?= $theme['fonts']['heading'] ?>, sans-serif;
      --font-body: <?= $theme['fonts']['body'] ?>, sans-serif;
    }
    body { font-family: var(--font-body); }
    h1, h2, h3 { font-family: var(--font-heading); color: var(--color-primary); }
  </style>
</head>
<body>
<?php

// Resolve nested collection item by ID
function resolveNestedItem($collectionName, $itemId){
  $itemPath = "cms/collections/{$collectionName}/{$itemId}.json";
  if(file_exists($itemPath)){
    return json_decode(file_get_contents($itemPath), true);
  }
  return null;
}

// Resolve nested collection references in data
function resolveNested($data){
  if(!is_array($data)) return $data;

  foreach($data as $key => $value){
    // Check if this is a nested collection reference (has 'id' key)
    if(is_array($value) && !empty($value)){
      if(isset($value[0]['id'])){
        // Array of nested items
        $resolved = [];
        foreach($value as $nestedRef){
          // Infer collection from context or use explicit collection name
          $collectionName = $nestedRef['collection'] ?? $key;
          $resolved[] = resolveNestedItem($collectionName, $nestedRef['id']);
        }
        $data[$key] = array_filter($resolved); // Remove nulls
      } else {
        // Recursively resolve nested structures
        $data[$key] = resolveNested($value);
      }
    }
  }

  return $data;
}

// Fetch items from a collection with multi-language support
function fetchCollection($collectionName, $limit = null){
  global $currentLang;
  $files = glob("cms/collections/{$collectionName}/*.json");
  $items = [];

  // Group files by base name (without language suffix)
  $filesByBase = [];
  foreach($files as $file){
    $basename = basename($file, '.json');

    // Check if file has language suffix (e.g., home.en.json)
    if(preg_match('/\.([a-z]{2})$/', $basename, $matches)){
      $lang = $matches[1];
      $base = preg_replace('/\.[a-z]{2}$/', '', $basename);
      $filesByBase[$base][$lang] = $file;
    } else {
      // Language-neutral file
      $filesByBase[$basename]['neutral'] = $file;
    }
  }

  // Load items in current language or fallback
  foreach($filesByBase as $base => $langFiles){
    $fileToLoad = null;

    // Try current language first
    if(isset($langFiles[$currentLang])){
      $fileToLoad = $langFiles[$currentLang];
    }
    // Fall back to neutral
    elseif(isset($langFiles['neutral'])){
      $fileToLoad = $langFiles['neutral'];
    }
    // Fall back to any available language
    else{
      $fileToLoad = reset($langFiles);
    }

    if($fileToLoad){
      $item = json_decode(file_get_contents($fileToLoad), true);
      // Resolve nested collection references
      $item = resolveNested($item);
      $items[] = $item;
    }
  }

  if($limit){
    $items = array_slice($items, 0, $limit);
  }
  return $items;
}

// Simple template renderer
function renderTemplate($type, $data){
  $templatePath = "cms/layouts/{$type}.json";
  if(!file_exists($templatePath)) return '';

  $template = json_decode(file_get_contents($templatePath), true);
  $html = $template['html'];

  // Handle conditional sections for optional fields {{#key}}...{{/key}}
  foreach($data as $key=>$value){
    if(!is_array($value)){
      $pattern = "/{{#{$key}}}(.+?){{\\/{$key}}}/s";
      if(preg_match($pattern, $html, $matches)){
        // If value exists and is not empty, keep the content
        if(!empty($value)){
          $html = str_replace($matches[0], $matches[1], $html);
        } else {
          // Remove the conditional section
          $html = str_replace($matches[0], '', $html);
        }
      }
    }
  }

  // Replace simple placeholders {{key}}
  foreach($data as $key=>$value){
    if(!is_array($value)){
      $html = str_replace("{{{$key}}}", $value, $html);
    }
  }

  // Handle array iteration for any array field (e.g., {{#items}}, {{#posts}})
  foreach($data as $key=>$value){
    if(is_array($value) && !empty($value)){
      $pattern = "/{{#{$key}}}(.+?){{\\/{$key}}}/s";
      preg_match($pattern, $html, $matches);
      if($matches){
        $itemTemplate = $matches[1];
        $itemsHtml = '';
        foreach($value as $item){
          if(is_array($item)){
            $itemHtml = $itemTemplate;
            foreach($item as $k=>$v){
              if(!is_array($v)){
                $itemHtml = str_replace("{{{$k}}}", $v, $itemHtml);
              } else {
                // Handle nested arrays recursively
                $nestedPattern = "/{{#{$k}}}(.+?){{\\/{$k}}}/s";
                preg_match($nestedPattern, $itemHtml, $nestedMatches);
                if($nestedMatches){
                  $nestedTemplate = $nestedMatches[1];
                  $nestedHtml = '';
                  foreach($v as $nestedItem){
                    if(is_array($nestedItem)){
                      $nestedItemHtml = $nestedTemplate;
                      foreach($nestedItem as $nk=>$nv){
                        if(!is_array($nv)){
                          $nestedItemHtml = str_replace("{{{$nk}}}", $nv, $nestedItemHtml);
                        }
                      }
                      $nestedHtml .= $nestedItemHtml;
                    }
                  }
                  $itemHtml = str_replace($nestedMatches[0], $nestedHtml, $itemHtml);
                }
              }
            }
            $itemsHtml .= $itemHtml;
          }
        }
        $html = str_replace($matches[0], $itemsHtml, $html);
      }
    }
  }

  return $html;
}

// Snapshot already loaded at the top for metadata extraction
foreach($snap as $item){
  if(isset($item['sections'])){
    // Multi-section page with templates
    echo "<div class='page'>";
    echo "<h1>{$item['title']}</h1>";
    foreach($item['sections'] as $section){
      // Multi-collection linking: fetch referenced collection data
      if(isset($section['collection'])){
        $section['items'] = fetchCollection($section['collection'], $section['limit'] ?? null);
      }
      echo renderTemplate($section['type'], $section);
    }
    echo "</div>";
  } else {
    // Simple page/item
    echo "<section><h1>{$item['title']}</h1></section>";
  }
}

// Language switcher
echo '<div style="position:fixed;top:10px;right:10px;background:white;padding:8px;border:1px solid #ccc;border-radius:4px;">';
echo '<strong>Language:</strong> ';
foreach($langConfig['supported'] as $lang){
  $active = $lang === $currentLang ? 'font-weight:bold;' : '';
  echo "<a href='?lang={$lang}' style='margin:0 4px;{$active}'>{$langConfig['labels'][$lang]}</a>";
}
echo '</div>';
?>
</body>
</html>

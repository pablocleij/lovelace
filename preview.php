<?php
// Load theme configuration
$theme = json_decode(file_get_contents('cms/config/theme.json'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Preview</title>
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

// Fetch items from a collection
function fetchCollection($collectionName, $limit = null){
  $files = glob("cms/collections/{$collectionName}/*.json");
  $items = [];
  foreach($files as $file){
    $item = json_decode(file_get_contents($file), true);
    // Resolve nested collection references
    $item = resolveNested($item);
    $items[] = $item;
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

$snap=json_decode(file_get_contents('cms/snapshots/latest.json'),true);
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
?>
</body>
</html>

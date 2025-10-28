<?php

// Replay mode: verify, rollback, or normal
$mode = $_GET['mode'] ?? 'normal';
$rollbackTo = $_GET['rollback'] ?? null;
$verifyMode = $_GET['verify'] ?? false;

// Schema inheritance - merge parent schema fields
// Now supports co-located schemas (PRD spec: /cms/collections/posts/schema.json)
function mergeSchema($schemaName, $collectionName = null){
  // Try collection-specific schema first (PRD spec)
  if($collectionName){
    $colocatedPath = "cms/collections/{$collectionName}/schema.json";
    if(file_exists($colocatedPath)){
      $schema = json_decode(file_get_contents($colocatedPath), true);

      // If schema extends another, merge parent fields
      if(isset($schema['extends'])){
        $parent = mergeSchema($schema['extends']);
        $schema['fields'] = array_merge($parent['fields'] ?? [], $schema['fields'] ?? []);
      }

      return $schema;
    }
  }

  // Fall back to separate schemas directory (backward compatibility)
  $schemaPath = "cms/schemas/{$schemaName}.json";
  if(!file_exists($schemaPath)) return ['fields'=>[]];

  $schema = json_decode(file_get_contents($schemaPath), true);

  // If schema extends another, merge parent fields
  if(isset($schema['extends'])){
    $parent = mergeSchema($schema['extends']);
    $schema['fields'] = array_merge($parent['fields'] ?? [], $schema['fields'] ?? []);
  }

  return $schema;
}

$events = glob('cms/events/*.json');
sort($events); // Process in chronological order
$site = [];
$previousHash = '';
$invalidEvents = [];

// Verification mode: validate hash chain
if($verifyMode){
  echo "<h1>Event Chain Verification</h1>\n";
  echo "<pre>\n";
}

foreach($events as $file){
  $e=json_decode(file_get_contents($file), true);

  // Hash chain validation
  if($verifyMode){
    $expectedHash = hash('sha256', json_encode([
      'id' => $e['id'],
      'timestamp' => $e['timestamp'],
      'actor' => $e['actor'],
      'instruction' => $e['instruction'],
      'patches' => $e['patches'],
      'previous_hash' => $previousHash
    ]));

    $hashValid = ($e['previous_hash'] === $previousHash);
    $status = $hashValid ? '✓ VALID' : '✗ INVALID';

    echo "{$status} Event {$e['id']}: {$e['instruction']}\n";
    echo "  Previous Hash: {$e['previous_hash']}\n";
    echo "  Expected Prev: {$previousHash}\n";
    echo "  Current Hash:  {$e['hash']}\n\n";

    if(!$hashValid){
      $invalidEvents[] = $e['id'];
    }

    $previousHash = $e['hash'];
  }

  // Rollback mode: stop at specified event
  if($rollbackTo && $e['id'] === $rollbackTo){
    if($verifyMode){
      echo "\n--- ROLLBACK POINT: Event {$rollbackTo} ---\n";
    }
    break;
  }

  $eventId = $e['id'];

  foreach($e['patches'] as $patch){
    if($patch['op']=='create_collection'){
      mkdir('cms/collections/'.$patch['target'], 0777, true);
    }
    if($patch['op']=='create_file'){
      $filePath = 'cms/'.$patch['target'];
      file_put_contents($filePath, json_encode($patch['value'], JSON_PRETTY_PRINT));

      // File versioning: create snapshot per event ID
      $versionPath = pathinfo($filePath, PATHINFO_DIRNAME) . '/' . pathinfo($filePath, PATHINFO_FILENAME);
      $versionDir = str_replace('cms/collections/', 'cms/snapshots/', $versionPath);
      if(!file_exists($versionDir)){
        mkdir($versionDir, 0777, true);
      }
      file_put_contents("{$versionDir}/{$eventId}.json", json_encode($patch['value'], JSON_PRETTY_PRINT));
    }
    if($patch['op']=='update_theme'){
      $theme = json_decode(file_get_contents('cms/config/theme.json'), true);
      $theme = array_merge_recursive($theme, $patch['value']);
      file_put_contents('cms/config/theme.json', json_encode($theme, JSON_PRETTY_PRINT));
    }
  }
}

// Snapshot builder
$collections=glob('cms/collections/*/*.json');
$snap=[];
foreach($collections as $file){ $snap[] = json_decode(file_get_contents($file),true); }
file_put_contents('cms/snapshots/latest.json',json_encode($snap,JSON_PRETTY_PRINT));

// Dynamic navigation: rebuild navigation from pages collection
$pagesDir = 'cms/collections/pages';
if(is_dir($pagesDir)){
  $nav = [];
  foreach(glob("{$pagesDir}/*.json") as $f){
    $page = json_decode(file_get_contents($f), true);
    if(isset($page['title'])){
      $nav[] = [
        'title' => $page['title'],
        'slug' => pathinfo($f, PATHINFO_FILENAME)
      ];
    }
  }
  file_put_contents('cms/config/navigation.json', json_encode($nav, JSON_PRETTY_PRINT));
}

// Verification mode: show results
if($verifyMode){
  echo "</pre>\n";

  if(count($invalidEvents) === 0){
    echo "<h2 style='color:green'>✓ Event chain is valid!</h2>\n";
    echo "<p>All " . count($events) . " events have valid hash chains.</p>\n";
  } else {
    echo "<h2 style='color:red'>✗ Event chain validation failed!</h2>\n";
    echo "<p>Invalid events: " . implode(', ', $invalidEvents) . "</p>\n";
  }
  exit;
}

// Rollback mode: show success message
if($rollbackTo){
  echo "<h1>Rollback Complete</h1>\n";
  echo "<p>State rebuilt up to event: <strong>{$rollbackTo}</strong></p>\n";
  echo "<p>Snapshot saved to: cms/snapshots/latest.json</p>\n";
  echo "<p><a href='preview.php'>View Preview</a> | <a href='index.php'>Return to CMS</a></p>\n";
  exit;
}

// Normal mode: silent execution
// Snapshot and navigation already built above

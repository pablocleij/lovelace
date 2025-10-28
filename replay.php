<?php

// Schema inheritance - merge parent schema fields
function mergeSchema($schemaName){
  $schemaPath = "cms/schemas/{$schemaName}.json";
  if(!file_exists($schemaPath)) return ['fields'=>[]];

  $schema = json_decode(file_get_contents($schemaPath), true);

  // If schema extends another, merge parent fields
  if(isset($schema['extends'])){
    $parent = mergeSchema($schema['extends']);
    $schema['fields'] = array_merge($parent['fields'], $schema['fields']);
  }

  return $schema;
}

$events = glob('cms/events/*.json');
sort($events); // Process in chronological order
$site = [];
// $pubKey = load from cms/config/keypair.pub
foreach($events as $file){
  $e=json_decode(file_get_contents($file), true);

  // Validate event signature before applying
  if(isset($e['signature'])){
    $sig=base64_decode($e['signature']);
    if(!sodium_crypto_sign_verify_detached($sig,json_encode($e),$pubKey)){
      die("Invalid event signature");
    }
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

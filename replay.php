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

  foreach($e['patches'] as $patch){
    if($patch['op']=='create_collection'){
      mkdir('cms/collections/'.$patch['target'], 0777, true);
    }
    if($patch['op']=='create_file'){
      file_put_contents('cms/'.$patch['target'], json_encode($patch['value'], JSON_PRETTY_PRINT));
    }
  }
}

// Snapshot builder
$collections=glob('cms/collections/*/*.json');
$snap=[];
foreach($collections as $file){ $snap[] = json_decode(file_get_contents($file),true); }
file_put_contents('cms/snapshots/latest.json',json_encode($snap,JSON_PRETTY_PRINT));

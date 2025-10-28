<?php
$events = glob('cms/events/*.json');
$site = [];
foreach($events as $file){
  $e=json_decode(file_get_contents($file), true);
  foreach($e['patches'] as $patch){
    if($patch['op']=='create_collection'){
      mkdir('cms/collections/'.$patch['target'], 0777, true);
    }
    if($patch['op']=='create_file'){
      file_put_contents('cms/'.$patch['target'], json_encode($patch['value'], JSON_PRETTY_PRINT));
    }
  }
}
file_put_contents('cms/snapshots/latest.json', json_encode($site,JSON_PRETTY_PRINT));

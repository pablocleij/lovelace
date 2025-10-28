<?php
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
file_put_contents('cms/snapshots/latest.json', json_encode($site,JSON_PRETTY_PRINT));

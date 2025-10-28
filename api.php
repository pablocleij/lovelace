<?php
header('Content-Type: application/json');
$apiConfig = json_decode(file_get_contents('cms/config/api_key.json'), true);
$apiKey = $apiConfig['key'];

function writeEvent($event){
  $prevHash = ''; // load previous hash if exists
  $event['previous_hash']=$prevHash;
  $event['hash']=hash('sha256', json_encode($event));
  $key = sodium_crypto_sign_keypair();
  $event['signature']=base64_encode(sodium_crypto_sign_detached(json_encode($event), $key));
  $id = str_pad(count(glob('cms/events/*.json'))+1,7,'0',STR_PAD_LEFT);
  file_put_contents("cms/events/$id.json", json_encode($event,JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents('php://input'), true);

$payload = [
  "model"=>"gpt-4o",
  "messages"=>[["role"=>"system","content"=>"You are lovelace..."],["role"=>"user","content"=>$data['message']]]
];
$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch,CURLOPT_HTTPHEADER,["Authorization: Bearer $apiKey","Content-Type: application/json"]);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
$res=json_decode(curl_exec($ch),true); curl_close($ch);

$aiMessage = $res['choices'][0]['message']['content'];
$response = json_decode($aiMessage,true);
writeEvent($response['event']);
echo json_encode(['message'=>$response['message'],'form'=>$response['form']??null]);

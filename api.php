<?php
header('Content-Type: application/json');
$apiConfig = json_decode(file_get_contents('cms/config/api_key.json'), true);
$apiKey = $apiConfig['key'];
$policy = json_decode(file_get_contents('cms/config/policy.json'), true);

// Build context from event history and site state
function buildContext(){
  $context = "You are lovelace, a conversational AI CMS.\n\n";

  // Include policy
  if(file_exists('cms/config/policy.json')){
    $policy = json_decode(file_get_contents('cms/config/policy.json'), true);
    $context .= "POLICY:\n" . json_encode($policy, JSON_PRETTY_PRINT) . "\n\n";
  }

  // Include recent events (last 5)
  $events = glob('cms/events/*.json');
  rsort($events);
  $recentEvents = array_slice($events, 0, 5);
  $context .= "RECENT ACTIONS:\n";
  foreach($recentEvents as $file){
    $event = json_decode(file_get_contents($file), true);
    $context .= "- {$event['instruction']}\n";
  }
  $context .= "\n";

  // Include collection summaries
  $collections = glob('cms/collections/*', GLOB_ONLYDIR);
  $context .= "COLLECTIONS:\n";
  foreach($collections as $dir){
    $name = basename($dir);
    $count = count(glob("$dir/*.json"));
    $context .= "- $name ($count items)\n";
  }
  $context .= "\n";

  // Include current theme
  if(file_exists('cms/config/theme.json')){
    $theme = json_decode(file_get_contents('cms/config/theme.json'), true);
    $context .= "THEME:\n" . json_encode($theme, JSON_PRETTY_PRINT) . "\n\n";
  }

  // Include navigation
  if(file_exists('cms/config/navigation.json')){
    $nav = json_decode(file_get_contents('cms/config/navigation.json'), true);
    $context .= "NAVIGATION: " . json_encode($nav) . "\n\n";
  }

  return $context;
}

// Auto-generate forms for missing required schema fields
function validateAndGenerateForm($schemaName, $data){
  $schemaPath = "cms/schemas/{$schemaName}.json";
  if(!file_exists($schemaPath)) return null;

  $schema = json_decode(file_get_contents($schemaPath), true);

  // Handle schema inheritance
  if(isset($schema['extends'])){
    $parentPath = "cms/schemas/{$schema['extends']}.json";
    if(file_exists($parentPath)){
      $parent = json_decode(file_get_contents($parentPath), true);
      $schema['fields'] = array_merge($parent['fields'] ?? [], $schema['fields']);
    }
  }

  $form = ['fields'=>[]];

  // Check for missing fields
  foreach($schema['fields'] as $f=>$type){
    if(!isset($data[$f])){
      $form['fields'][]=["name"=>$f,"label"=>$f,"type"=>$type];
    }
  }

  // Return form only if there are missing fields
  return count($form['fields']) > 0 ? $form : null;
}

function writeEvent($event){
  $prevHash = ''; // load previous hash if exists
  $event['previous_hash']=$prevHash;
  $event['hash']=hash('sha256', json_encode($event));
  $key = sodium_crypto_sign_keypair();
  $event['signature']=base64_encode(sodium_crypto_sign_detached(json_encode($event), $key));
  $id = str_pad(count(glob('cms/events/*.json'))+1,7,'0',STR_PAD_LEFT);
  file_put_contents("cms/events/$id.json", json_encode($event,JSON_PRETTY_PRINT));

  // Event audit log
  file_put_contents('cms/logs/audit.log', date('c').' '.$event['instruction']."\n", FILE_APPEND);
}

$data = json_decode(file_get_contents('php://input'), true);

// Build proactive memory context from event history
$contextPrompt = buildContext();

$payload = [
  "model"=>"gpt-4o",
  "messages"=>[["role"=>"system","content"=>$contextPrompt],["role"=>"user","content"=>$data['message']]]
];
$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch,CURLOPT_HTTPHEADER,["Authorization: Bearer $apiKey","Content-Type: application/json"]);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
$res=json_decode(curl_exec($ch),true); curl_close($ch);

$aiMessage = $res['choices'][0]['message']['content'];
$response = json_decode($aiMessage,true);

// AI checks policy before applying patch
foreach($response['event']['patches'] ?? [] as $patch){
  if(in_array($patch['op'], $policy['require_confirmation_for'])){
    // Requires user confirmation
    $response['requires_confirmation'] = true;
  }

  // Auto-generate forms for collection items with missing schema fields
  if($patch['op']=='create_file' && isset($patch['schema'])){
    $generatedForm = validateAndGenerateForm($patch['schema'], $patch['value']);
    if($generatedForm){
      $response['form'] = array_merge($response['form'] ?? [], $generatedForm);
    }
  }
}

writeEvent($response['event']);

if(in_array('add_to_navigation',$response['suggestions'])){
  $nav=json_decode(file_get_contents('cms/config/navigation.json'),true);
  $nav[]=$response['page'];
  file_put_contents('cms/config/navigation.json',json_encode($nav,JSON_PRETTY_PRINT));
}

// Proactive AI suggestions
if($policy['suggest_improvements']){
  $suggest=['Add SEO meta','Group navigation','Add footer'];
  $response['suggestions'] = array_merge($response['suggestions'] ?? [], $suggest);
}

echo json_encode(['message'=>$response['message'],'form'=>$response['form']??null,'suggestions'=>$response['suggestions']??[]]);

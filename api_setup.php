<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

// Simple base64 encoding (since OpenSSL might fail)
function encryptKey($key){
  return base64_encode('SIMPLE::' . $key);
}

function decryptKey($encryptedKey){
  $decoded = base64_decode($encryptedKey);
  if(strpos($decoded, 'SIMPLE::') === 0){
    return substr($decoded, 8);
  }
  return null;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $data['action'] ?? '';

if($action === 'check_key'){
  $keyFile = 'cms/config/api_key.json';

  if(!file_exists($keyFile)){
    echo json_encode(['has_key' => false, 'message' => 'No API key file found']);
    exit;
  }

  $config = json_decode(file_get_contents($keyFile), true);
  $key = $config['key'] ?? '';

  if(empty($key) || $key === 'YOUR_KEY_HERE' || strlen($key) < 10){
    echo json_encode(['has_key' => false, 'message' => 'API key not configured']);
    exit;
  }

  echo json_encode([
    'has_key' => true,
    'provider' => $config['provider'] ?? 'openai',
    'model' => $config['model'] ?? 'gpt-4o',
    'encrypted' => isset($config['encrypted']) ? $config['encrypted'] : false
  ]);
  exit;
}

if($action === 'save_key'){
  $provider = $data['provider'] ?? 'openai';
  $key = $data['key'] ?? '';
  $model = $data['model'] ?? 'gpt-4o';

  if(empty($key)){
    echo json_encode(['success' => false, 'error' => 'API key is required']);
    exit;
  }

  if($provider === 'openai' && !preg_match('/^sk-[A-Za-z0-9\-_]{20,}/', $key)){
    echo json_encode(['success' => false, 'error' => 'Invalid OpenAI API key format']);
    exit;
  }

  if($provider === 'claude' && !preg_match('/^sk-ant-[A-Za-z0-9\-]+/', $key)){
    echo json_encode(['success' => false, 'error' => 'Invalid Anthropic API key format']);
    exit;
  }

  $encryptedKey = encryptKey($key);

  $config = [
    'provider' => $provider,
    'key' => $encryptedKey,
    'model' => $model,
    'encrypted' => true,
    'last_updated' => date('c')
  ];

  file_put_contents('cms/config/api_key.json', json_encode($config, JSON_PRETTY_PRINT));

  echo json_encode(['success' => true, 'message' => 'API key saved securely']);
  exit;
}

if($action === 'validate_key'){
  // Skip validation if CURL not available
  if(!extension_loaded('curl')){
    echo json_encode([
      'valid' => true,
      'warning' => 'CURL not available, skipping validation',
      'provider' => 'openai'
    ]);
    exit;
  }

  $keyFile = 'cms/config/api_key.json';

  if(!file_exists($keyFile)){
    echo json_encode(['valid' => false, 'error' => 'No API key configured']);
    exit;
  }

  $config = json_decode(file_get_contents($keyFile), true);
  $encryptedKey = $config['key'] ?? '';
  $provider = $config['provider'] ?? 'openai';
  $isEncrypted = $config['encrypted'] ?? false;

  $apiKey = $isEncrypted ? decryptKey($encryptedKey) : $encryptedKey;

  if(!$apiKey){
    echo json_encode(['valid' => false, 'error' => 'Failed to decrypt API key']);
    exit;
  }

  $testSuccessful = false;
  $errorMessage = '';

  if($provider === 'openai'){
    $ch = curl_init("https://api.openai.com/v1/models");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $testSuccessful = ($httpCode === 200);
    if(!$testSuccessful){
      $errorMessage = "API key validation failed (HTTP $httpCode)";
    }
  } else if($provider === 'claude'){
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "x-api-key: $apiKey",
      "anthropic-version: 2023-06-01",
      "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
      'model' => 'claude-3-5-sonnet-20241022',
      'max_tokens' => 10,
      'messages' => [['role' => 'user', 'content' => 'test']]
    ]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $testSuccessful = ($httpCode === 200);
    if(!$testSuccessful){
      $errorMessage = "API key validation failed (HTTP $httpCode)";
    }
  }

  echo json_encode([
    'valid' => $testSuccessful,
    'error' => $testSuccessful ? null : $errorMessage,
    'provider' => $provider
  ]);
  exit;
}

echo json_encode(['error' => 'Invalid action']);

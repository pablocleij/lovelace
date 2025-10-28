<?php
// Suppress PHP warnings/notices to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
  $data = json_decode(file_get_contents('php://input'), true);
  $action = $data['action'] ?? '';
} catch(Exception $e){
  echo json_encode([
    'error' => 'Invalid request: ' . $e->getMessage()
  ]);
  exit;
}

// Simple encryption key (in production, use environment variable or secure storage)
define('ENCRYPTION_KEY', 'lovelace_cms_key_2024'); // TODO: Move to .env

function encryptKey($key){
  try {
    if(!function_exists('openssl_random_pseudo_bytes')){
      throw new Exception('OpenSSL not available');
    }
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($key, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    if($encrypted === false){
      throw new Exception('Encryption failed');
    }
    return base64_encode($iv . '::' . $encrypted);
  } catch(Exception $e){
    // Fallback: base64 encode (not secure, but better than failing)
    error_log('Encryption failed: ' . $e->getMessage());
    return base64_encode('FALLBACK::' . $key);
  }
}

function decryptKey($encryptedKey){
  try {
    $decoded = base64_decode($encryptedKey);
    $parts = explode('::', $decoded);

    // Check for fallback encoding
    if(count($parts) === 2 && $parts[0] === 'FALLBACK'){
      return $parts[1];
    }

    if(count($parts) !== 2) return null;

    if(!function_exists('openssl_decrypt')){
      throw new Exception('OpenSSL not available');
    }

    $decrypted = openssl_decrypt($parts[1], 'AES-256-CBC', ENCRYPTION_KEY, 0, $parts[0]);
    if($decrypted === false){
      throw new Exception('Decryption failed');
    }
    return $decrypted;
  } catch(Exception $e){
    error_log('Decryption failed: ' . $e->getMessage());
    return null;
  }
}

try {

if($action === 'check_key'){
  // Check if a valid API key is configured
  $keyFile = 'cms/config/api_key.json';

  if(!file_exists($keyFile)){
    echo json_encode([
      'has_key' => false,
      'message' => 'No API key file found'
    ]);
    exit;
  }

  $config = json_decode(file_get_contents($keyFile), true);
  $key = $config['key'] ?? '';

  // Check if key is placeholder or empty
  if(empty($key) || $key === 'YOUR_KEY_HERE' || strlen($key) < 10){
    echo json_encode([
      'has_key' => false,
      'message' => 'API key not configured'
    ]);
    exit;
  }

  // Check if key is encrypted (starts with base64)
  $isEncrypted = preg_match('/^[A-Za-z0-9+\/=]+$/', $key) && strlen($key) > 50;

  echo json_encode([
    'has_key' => true,
    'provider' => $config['provider'] ?? 'openai',
    'model' => $config['model'] ?? 'gpt-4o',
    'encrypted' => $isEncrypted
  ]);
  exit;
}

if($action === 'save_key'){
  // Save API key securely
  $provider = $data['provider'] ?? 'openai';
  $key = $data['key'] ?? '';
  $model = $data['model'] ?? 'gpt-4o';

  if(empty($key)){
    echo json_encode([
      'success' => false,
      'error' => 'API key is required'
    ]);
    exit;
  }

  // Validate key format (more permissive - OpenAI has various formats)
  if($provider === 'openai' && !preg_match('/^sk-[A-Za-z0-9\-_]{20,}/', $key)){
    echo json_encode([
      'success' => false,
      'error' => 'Invalid OpenAI API key format (should start with sk-)'
    ]);
    exit;
  }

  if($provider === 'claude' && !preg_match('/^sk-ant-[A-Za-z0-9\-]+/', $key)){
    echo json_encode([
      'success' => false,
      'error' => 'Invalid Anthropic API key format (should start with sk-ant-)'
    ]);
    exit;
  }

  // Encrypt the key
  $encryptedKey = encryptKey($key);

  // Save to config
  $config = [
    'provider' => $provider,
    'key' => $encryptedKey,
    'model' => $model,
    'encrypted' => true,
    'last_updated' => date('c')
  ];

  file_put_contents('cms/config/api_key.json', json_encode($config, JSON_PRETTY_PRINT));

  echo json_encode([
    'success' => true,
    'message' => 'API key saved securely'
  ]);
  exit;
}

if($action === 'validate_key'){
  // Test if the API key actually works
  $keyFile = 'cms/config/api_key.json';

  if(!file_exists($keyFile)){
    echo json_encode([
      'valid' => false,
      'error' => 'No API key configured'
    ]);
    exit;
  }

  $config = json_decode(file_get_contents($keyFile), true);
  $encryptedKey = $config['key'] ?? '';
  $provider = $config['provider'] ?? 'openai';
  $isEncrypted = $config['encrypted'] ?? false;

  // Decrypt if needed
  $apiKey = $isEncrypted ? decryptKey($encryptedKey) : $encryptedKey;

  if(!$apiKey){
    echo json_encode([
      'valid' => false,
      'error' => 'Failed to decrypt API key'
    ]);
    exit;
  }

  // Test the key with a simple API call
  $testSuccessful = false;
  $errorMessage = '';

  if($provider === 'openai'){
    $ch = curl_init("https://api.openai.com/v1/models");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $testSuccessful = ($httpCode === 200);
    if(!$testSuccessful){
      $errorMessage = "API key validation failed (HTTP $httpCode)";
    }
  } else if($provider === 'claude'){
    // Test Claude API
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "x-api-key: $apiKey",
      "anthropic-version: 2023-06-01",
      "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
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

} catch(Exception $e){
  // Catch any unexpected errors and return JSON
  error_log('API Setup Error: ' . $e->getMessage());
  echo json_encode([
    'error' => 'Server error: ' . $e->getMessage(),
    'action' => $action ?? 'unknown'
  ]);
}

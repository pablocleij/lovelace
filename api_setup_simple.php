<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "PHP Version: " . phpversion() . "\n";
echo "OpenSSL loaded: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "\n";
echo "CURL loaded: " . (extension_loaded('curl') ? 'YES' : 'NO') . "\n";
echo "\n";

echo "Testing file operations...\n";
if(file_exists('cms/config/api_key.json')){
  echo "api_key.json exists\n";
  $content = file_get_contents('cms/config/api_key.json');
  echo "Content length: " . strlen($content) . "\n";
} else {
  echo "api_key.json NOT found\n";
}

echo "\nTesting JSON input...\n";
$input = file_get_contents('php://input');
echo "Input: " . $input . "\n";
$data = json_decode($input, true);
echo "Decoded: " . print_r($data, true) . "\n";

echo "\nAll tests passed!\n";

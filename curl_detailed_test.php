<?php
header('Content-Type: text/plain');

echo "=== CURL Detection Tests ===\n\n";

// Test 1: extension_loaded
echo "1. extension_loaded('curl'): ";
echo extension_loaded('curl') ? 'TRUE' : 'FALSE';
echo "\n\n";

// Test 2: function_exists
echo "2. function_exists('curl_init'): ";
echo function_exists('curl_init') ? 'TRUE' : 'FALSE';
echo "\n\n";

// Test 3: class_exists
echo "3. class_exists('CURLFile'): ";
echo class_exists('CURLFile') ? 'TRUE' : 'FALSE';
echo "\n\n";

// Test 4: get_loaded_extensions
echo "4. Loaded extensions:\n";
$extensions = get_loaded_extensions();
$curlFound = false;
foreach($extensions as $ext){
  if(stripos($ext, 'curl') !== false){
    echo "   FOUND: $ext\n";
    $curlFound = true;
  }
}
if(!$curlFound){
  echo "   CURL not in loaded extensions list\n";
}
echo "\n";

// Test 5: Try to actually use curl
echo "5. Trying curl_init()...\n";
if(function_exists('curl_init')){
  $ch = curl_init();
  if($ch){
    echo "   SUCCESS: curl_init() worked!\n";
    curl_close($ch);
  } else {
    echo "   FAILED: curl_init() returned false\n";
  }
} else {
  echo "   FAILED: curl_init() function doesn't exist\n";
}
echo "\n";

// Test 6: PHP version and config
echo "6. PHP Info:\n";
echo "   Version: " . phpversion() . "\n";
echo "   Config file: " . php_ini_loaded_file() . "\n";
echo "   Extension dir: " . ini_get('extension_dir') . "\n";

echo "\n=== END TESTS ===\n";

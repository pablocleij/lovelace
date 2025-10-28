<?php
header('Content-Type: text/plain');

echo "=== CURL TROUBLESHOOTING ===\n\n";

echo "1. PHP INI FILE USED BY WEB:\n";
$iniFile = php_ini_loaded_file();
echo "   $iniFile\n\n";

echo "2. CHECKING IF CURL LINE EXISTS IN INI:\n";
if(file_exists($iniFile)){
    $iniContent = file_get_contents($iniFile);

    // Find curl lines
    $lines = explode("\n", $iniContent);
    $curlLines = [];
    foreach($lines as $num => $line){
        if(stripos($line, 'curl') !== false){
            $curlLines[] = ($num + 1) . ": " . trim($line);
        }
    }

    if(!empty($curlLines)){
        echo "   FOUND these curl-related lines:\n";
        foreach($curlLines as $line){
            echo "   $line\n";
        }
    } else {
        echo "   NO curl lines found in php.ini!\n";
    }
} else {
    echo "   INI file not found!\n";
}

echo "\n3. EXTENSION DIRECTORY:\n";
$extDir = ini_get('extension_dir');
echo "   $extDir\n";

echo "\n4. CHECKING IF php_curl.dll EXISTS:\n";
$curlDll = $extDir . DIRECTORY_SEPARATOR . 'php_curl.dll';
if(file_exists($curlDll)){
    echo "   YES: $curlDll\n";
    echo "   File size: " . filesize($curlDll) . " bytes\n";
} else {
    echo "   NO: $curlDll NOT FOUND!\n";
}

echo "\n5. CHECKING FOR DEPENDENCIES:\n";
// Check for common curl dependencies
$dependencies = [
    'libeay32.dll',
    'ssleay32.dll',
    'libcurl.dll',
    'libssh2.dll'
];

$phpDir = dirname($iniFile);
foreach($dependencies as $dep){
    $depPath = $phpDir . DIRECTORY_SEPARATOR . $dep;
    if(file_exists($depPath)){
        echo "   ✓ $dep found\n";
    } else {
        echo "   ✗ $dep MISSING\n";
    }
}

echo "\n6. ERROR LOG CHECK:\n";
$errorLog = ini_get('error_log');
echo "   Error log location: $errorLog\n";
if(file_exists($errorLog)){
    $recent = file($errorLog);
    $recent = array_slice($recent, -20); // Last 20 lines
    $hasError = false;
    foreach($recent as $line){
        if(stripos($line, 'curl') !== false){
            echo "   >>> $line";
            $hasError = true;
        }
    }
    if(!$hasError){
        echo "   No recent curl-related errors\n";
    }
} else {
    echo "   Error log not found or not configured\n";
}

echo "\n=== SUGGESTED FIX ===\n";
echo "Based on the checks above:\n\n";

echo "1. Open php.ini at: $iniFile\n";
echo "2. Find the line with 'extension=curl'\n";
echo "3. Make sure it says EXACTLY: extension=curl\n";
echo "   (no semicolon, no extra spaces, no .dll extension)\n";
echo "4. Save the file\n";
echo "5. Restart Laragon (not just Apache - fully quit and restart Laragon)\n";
echo "6. Refresh this page\n\n";

echo "If curl still doesn't load, check if any dependencies are missing above.\n";

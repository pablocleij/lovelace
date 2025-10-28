<?php
// DEBUG VERSION - Show all errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Starting...\n";

header('Content-Type: application/json');

echo "Headers set...\n";

// Simple encryption key
define('ENCRYPTION_KEY', 'lovelace_cms_key_2024');

echo "Constant defined...\n";

$input = file_get_contents('php://input');
echo "Input read: " . substr($input, 0, 100) . "\n";

$data = json_decode($input, true);
echo "JSON decoded...\n";

$action = $data['action'] ?? 'none';
echo "Action: " . $action . "\n";

if($action === 'check_key'){
  echo json_encode(['has_key' => false, 'debug' => 'Debug mode active']);
  exit;
}

echo json_encode(['error' => 'Invalid action', 'action' => $action]);

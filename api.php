<?php
header('Content-Type: application/json');
$apiConfig = json_decode(file_get_contents('cms/config/api_key.json'), true);
$apiKey = $apiConfig['key'];
$data = json_decode(file_get_contents('php://input'), true);
echo json_encode(['message'=>"Echo: ".$data['message']]);

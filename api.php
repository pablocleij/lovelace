<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
echo json_encode(['message'=>"Echo: ".$data['message']]);

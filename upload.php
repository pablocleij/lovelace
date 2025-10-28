<?php
header('Content-Type: application/json');

// Asset upload handler
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['asset'])){
  $file = $_FILES['asset'];

  // Validate file
  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
  $maxSize = 5 * 1024 * 1024; // 5MB

  if($file['error'] !== UPLOAD_ERR_OK){
    echo json_encode(['error' => 'Upload failed']);
    exit;
  }

  if($file['size'] > $maxSize){
    echo json_encode(['error' => 'File too large (max 5MB)']);
    exit;
  }

  if(!in_array($file['type'], $allowedTypes)){
    echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, SVG, WebP']);
    exit;
  }

  // Determine asset category from type
  $category = 'images';
  if(strpos($file['type'], 'svg') !== false){
    $category = 'icons';
  }

  // Generate safe filename
  $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
  $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
  $filename = $safeFilename . '_' . time() . '.' . $extension;

  // Move to assets directory
  $uploadPath = "assets/{$category}/{$filename}";
  if(move_uploaded_file($file['tmp_name'], $uploadPath)){
    echo json_encode([
      'success' => true,
      'path' => "/{$uploadPath}",
      'message' => "Asset uploaded successfully"
    ]);
  } else {
    echo json_encode(['error' => 'Failed to save file']);
  }
} else {
  echo json_encode(['error' => 'No file uploaded']);
}

<?php
// Initialize CMS: run replay and generate snapshot if needed

// Check if snapshot exists
if(!file_exists('cms/snapshots/latest.json')){
  // Create snapshots directory if needed
  if(!file_exists('cms/snapshots')){
    mkdir('cms/snapshots', 0777, true);
  }

  // Run replay to generate snapshot
  include 'replay.php';

  // If still no snapshot (no events yet), create empty snapshot
  if(!file_exists('cms/snapshots/latest.json')){
    file_put_contents('cms/snapshots/latest.json', json_encode([], JSON_PRETTY_PRINT));
  }
}

// Return success
echo json_encode([
  'success' => true,
  'message' => 'CMS initialized',
  'snapshot_exists' => file_exists('cms/snapshots/latest.json')
]);

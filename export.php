<?php
// Export entire CMS as downloadable .zip file

$zipFilename = 'lovelace-export-' . date('Y-m-d-His') . '.zip';
$zipPath = sys_get_temp_dir() . '/' . $zipFilename;

// Create new zip archive
$zip = new ZipArchive();

if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
  die('Failed to create zip archive');
}

// Recursive function to add directory contents to zip
function addDirectoryToZip($zip, $dir, $base = ''){
  $files = glob($dir . '/*');

  foreach($files as $file){
    $relativePath = $base . basename($file);

    if(is_dir($file)){
      // Add directory
      $zip->addEmptyDir($relativePath);
      // Recursively add contents
      addDirectoryToZip($zip, $file, $relativePath . '/');
    } else {
      // Add file
      $zip->addFile($file, $relativePath);
    }
  }
}

// Add CMS directory structure
addDirectoryToZip($zip, 'cms', 'cms/');

// Add README with export info
$readme = "lovelace CMS Export\n";
$readme .= "==================\n\n";
$readme .= "Export Date: " . date('Y-m-d H:i:s') . "\n";
$readme .= "Total Events: " . count(glob('cms/events/*.json')) . "\n";
$readme .= "Collections: " . count(glob('cms/collections/*', GLOB_ONLYDIR)) . "\n\n";
$readme .= "To restore this export:\n";
$readme .= "1. Extract this zip file\n";
$readme .= "2. Copy the 'cms' directory to your lovelace installation\n";
$readme .= "3. Run replay.php to rebuild snapshots\n";
$zip->addFromString('README.txt', $readme);

// Close zip
$zip->close();

// Download the file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($zipPath));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipPath);

// Clean up temporary file
unlink($zipPath);
exit;

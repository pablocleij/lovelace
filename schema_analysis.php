<?php
header('Content-Type: application/json');

// Include the schema inference functions from api.php
require_once 'api.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'analyze_all';

if($action === 'analyze_collection'){
  // Analyze a specific collection
  $collectionName = $data['collection'] ?? null;

  if(!$collectionName){
    echo json_encode(['error' => 'Collection name required']);
    exit;
  }

  $analysis = analyzeCollectionSchema($collectionName);

  if(!$analysis){
    echo json_encode(['error' => 'Collection not found or empty']);
    exit;
  }

  echo json_encode($analysis, JSON_PRETTY_PRINT);
  exit;
}

if($action === 'analyze_all'){
  // Analyze all collections
  $results = [];
  $collections = glob('cms/collections/*', GLOB_ONLYDIR);

  foreach($collections as $collectionPath){
    $collectionName = basename($collectionPath);
    $analysis = analyzeCollectionSchema($collectionName);

    if($analysis){
      $results[$collectionName] = $analysis;
    }
  }

  echo json_encode([
    'collections' => $results,
    'summary' => [
      'total' => count($collections),
      'analyzed' => count($results),
      'issues_found' => array_sum(array_map(function($r){
        return count($r['suggestions']);
      }, $results))
    ]
  ], JSON_PRETTY_PRINT);
  exit;
}

if($action === 'infer_schema'){
  // Infer schema from collection items
  $collectionName = $data['collection'] ?? null;

  if(!$collectionName){
    echo json_encode(['error' => 'Collection name required']);
    exit;
  }

  $collectionPath = "cms/collections/{$collectionName}";
  if(!is_dir($collectionPath)){
    echo json_encode(['error' => 'Collection not found']);
    exit;
  }

  // Load all items
  $items = [];
  foreach(glob("{$collectionPath}/*.json") as $file){
    if(basename($file) === 'schema.json') continue;
    $items[] = json_decode(file_get_contents($file), true);
  }

  if(empty($items)){
    echo json_encode(['error' => 'No items to analyze']);
    exit;
  }

  $inferredSchema = inferSchemaFromContent($items);

  echo json_encode([
    'collection' => $collectionName,
    'items_analyzed' => count($items),
    'inferred_schema' => $inferredSchema
  ], JSON_PRETTY_PRINT);
  exit;
}

echo json_encode(['error' => 'Invalid action']);

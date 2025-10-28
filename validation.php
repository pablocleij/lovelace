<?php
// Comprehensive patch validation and error recovery

function validatePatchOperation($patch){
  $errors = [];
  $warnings = [];

  // Check required fields
  if(!isset($patch['op'])){
    $errors[] = "Missing required field 'op'";
    return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
  }

  $op = $patch['op'];

  // Validate operation-specific requirements
  switch($op){
    case 'create_collection':
      if(!isset($patch['target'])){
        $errors[] = "create_collection requires 'target' (collection name)";
      }
      if(isset($patch['schema']) && !isset($patch['schema']['fields'])){
        $warnings[] = "Schema provided but has no 'fields' property";
      }
      break;

    case 'create_file':
    case 'update_file':
      if(!isset($patch['target'])){
        $errors[] = "{$op} requires 'target' (file path)";
      }
      if(!isset($patch['value'])){
        $errors[] = "{$op} requires 'value' (content)";
      }
      if(isset($patch['target']) && !preg_match('/\.json$/', $patch['target'])){
        $warnings[] = "File path should end with .json";
      }
      break;

    case 'delete_file':
    case 'delete_collection':
      if(!isset($patch['target'])){
        $errors[] = "{$op} requires 'target'";
      }
      break;

    case 'update_schema':
    case 'update_config':
      if(!isset($patch['target'])){
        $errors[] = "{$op} requires 'target'";
      }
      if(!isset($patch['value'])){
        $errors[] = "{$op} requires 'value'";
      }
      break;

    case 'add_field_to_schema':
      if(!isset($patch['target'])){
        $errors[] = "add_field_to_schema requires 'target' (collection name)";
      }
      if(!isset($patch['field'])){
        $errors[] = "add_field_to_schema requires 'field' (field name)";
      }
      if(!isset($patch['type'])){
        $errors[] = "add_field_to_schema requires 'type' (field type)";
      }
      $validTypes = ['string', 'number', 'boolean', 'list', 'object'];
      if(isset($patch['type']) && !in_array($patch['type'], $validTypes)){
        $errors[] = "Invalid field type '{$patch['type']}'. Must be one of: " . implode(', ', $validTypes);
      }
      break;

    case 'update_theme':
    case 'update_navigation':
      if(!isset($patch['value'])){
        $errors[] = "{$op} requires 'value'";
      }
      break;

    case 'create_collection_item':
      if(!isset($patch['target'])){
        $errors[] = "create_collection_item requires 'target' (collection name)";
      }
      if(!isset($patch['value'])){
        $errors[] = "create_collection_item requires 'value' (item data)";
      }
      break;

    default:
      $errors[] = "Unknown operation '{$op}'";
  }

  return [
    'valid' => count($errors) === 0,
    'errors' => $errors,
    'warnings' => $warnings
  ];
}

function validateEvent($event){
  $errors = [];
  $warnings = [];

  // Check required event fields
  $requiredFields = ['id', 'timestamp', 'actor', 'instruction', 'patches'];
  foreach($requiredFields as $field){
    if(!isset($event[$field])){
      $errors[] = "Missing required event field '{$field}'";
    }
  }

  // Validate patches array
  if(isset($event['patches'])){
    if(!is_array($event['patches'])){
      $errors[] = "'patches' must be an array";
    } else {
      foreach($event['patches'] as $i => $patch){
        $validation = validatePatchOperation($patch);
        if(!$validation['valid']){
          foreach($validation['errors'] as $error){
            $errors[] = "Patch #{$i}: {$error}";
          }
        }
        foreach($validation['warnings'] as $warning){
          $warnings[] = "Patch #{$i}: {$warning}";
        }
      }
    }
  }

  // Validate timestamp format
  if(isset($event['timestamp'])){
    if(!strtotime($event['timestamp'])){
      $warnings[] = "Timestamp format may be invalid";
    }
  }

  return [
    'valid' => count($errors) === 0,
    'errors' => $errors,
    'warnings' => $warnings
  ];
}

function recoverFromError($error, $context = []){
  $recovery = [
    'recovered' => false,
    'action' => 'none',
    'message' => ''
  ];

  // File not found errors
  if(strpos($error, 'file_get_contents') !== false || strpos($error, 'No such file') !== false){
    if(isset($context['file_path'])){
      $recovery['action'] = 'create_missing_file';
      $recovery['message'] = "File missing, can create with default content";
      $recovery['recovered'] = true;
    }
  }

  // JSON decode errors
  if(strpos($error, 'JSON') !== false || strpos($error, 'json_decode') !== false){
    $recovery['action'] = 'fix_json_format';
    $recovery['message'] = "Invalid JSON, can attempt to fix or recreate";
    $recovery['recovered'] = true;
  }

  // Permission errors
  if(strpos($error, 'Permission denied') !== false || strpos($error, 'mkdir') !== false){
    $recovery['action'] = 'check_permissions';
    $recovery['message'] = "Permission error, check directory write permissions";
    $recovery['recovered'] = false;
  }

  // Schema validation errors
  if(strpos($error, 'schema') !== false || strpos($error, 'validation') !== false){
    $recovery['action'] = 'infer_schema';
    $recovery['message'] = "Schema issue detected, can infer schema from content";
    $recovery['recovered'] = true;
  }

  return $recovery;
}

function sanitizePatchValue($value, $type = null){
  // Sanitize based on expected type
  if($type === 'string'){
    return is_string($value) ? trim($value) : strval($value);
  }

  if($type === 'number'){
    return is_numeric($value) ? floatval($value) : 0;
  }

  if($type === 'boolean'){
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
  }

  if($type === 'list'){
    return is_array($value) ? array_values($value) : [];
  }

  if($type === 'object'){
    return is_array($value) ? $value : [];
  }

  // Default: return as-is
  return $value;
}

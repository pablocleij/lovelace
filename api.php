<?php
header('Content-Type: application/json');
$apiConfig = json_decode(file_get_contents('cms/config/api_key.json'), true);
$apiKey = $apiConfig['key'];
$provider = $apiConfig['provider'] ?? 'openai';
$model = $apiConfig['model'] ?? 'gpt-4o';
$policy = json_decode(file_get_contents('cms/config/policy.json'), true);

// Build context from event history and site state
function buildContext(){
  $context = "You are lovelace, a conversational AI CMS.\n\n";

  // CRITICAL: Enforce structured JSON response format
  $context .= "RESPONSE FORMAT (MANDATORY):\n";
  $context .= "You MUST respond with valid JSON in this exact structure:\n";
  $context .= "{\n";
  $context .= "  \"message\": \"Human-readable response\",\n";
  $context .= "  \"event\": {\n";
  $context .= "    \"id\": \"auto-generated\",\n";
  $context .= "    \"timestamp\": \"ISO-8601\",\n";
  $context .= "    \"actor\": \"ai-agent\",\n";
  $context .= "    \"instruction\": \"user's original request\",\n";
  $context .= "    \"patches\": [{\"op\": \"operation\", \"target\": \"path\", \"value\": {}}]\n";
  $context .= "  },\n";
  $context .= "  \"form\": null or {\"fields\": []},\n";
  $context .= "  \"scored_suggestions\": [],\n";
  $context .= "  \"section_suggestions\": []\n";
  $context .= "}\n\n";
  $context .= "NEVER return plain text. ALWAYS return valid JSON matching this structure.\n\n";

  // Proactive AI behavior instructions
  $context .= "BEHAVIORAL RULES (CRITICAL):\n";
  $context .= "1. CONFIRMATION - Before destructive operations (delete, overwrite), set requires_confirmation: true\n";
  $context .= "2. CLARIFICATION - When user request is ambiguous, return a form with fields to gather needed info\n";
  $context .= "3. SUGGESTIONS - After completing actions, suggest logical next steps in scored_suggestions array\n";
  $context .= "4. REFLECTION - Analyze site structure and proactively suggest improvements\n";
  $context .= "5. EDUCATION - Explain WHY you're suggesting something in the reason field\n";
  $context .= "6. DYNAMIC COLLECTIONS - When user mentions content types not yet existing, create them automatically\n\n";

  $context .= "CONFIRMATION TRIGGERS:\n";
  $context .= "- Deleting pages, collections, or content\n";
  $context .= "- Overwriting existing files\n";
  $context .= "- Changing theme colors or fonts drastically\n";
  $context .= "- Removing navigation items\n";
  $context .= "Response: Set requires_confirmation: true and provide confirmation_message\n\n";

  $context .= "CLARIFICATION TRIGGERS:\n";
  $context .= "- User says 'create blog' but doesn't specify fields\n";
  $context .= "- User says 'add image' but doesn't provide file\n";
  $context .= "- User says 'change colors' but doesn't specify which colors\n";
  $context .= "Response: Return form with fields array, provide smart defaults\n\n";

  $context .= "DYNAMIC COLLECTION CREATION TRIGGERS:\n";
  $context .= "- User mentions 'testimonials' but collection doesn't exist → create testimonials collection\n";
  $context .= "- User says 'add team members' → create team collection with name, role, bio, photo fields\n";
  $context .= "- User says 'create products section' → create products collection with appropriate schema\n";
  $context .= "- User mentions 'FAQ', 'portfolio', 'events', 'services' → create corresponding collections\n";
  $context .= "Response: Use create_collection with inferred schema, add 1 example item, return form for real data\n\n";

  $context .= "SUGGESTION EXAMPLES:\n";
  $context .= "- After creating homepage: Suggest 'Add an about page' or 'Create a blog section'\n";
  $context .= "- After adding blog: Suggest 'Create your first blog post' or 'Add categories'\n";
  $context .= "- After adding contact form: Suggest 'Test the form' or 'Add form validation'\n";
  $context .= "- Analyze missing elements: testimonials, FAQ, pricing, team, gallery, etc.\n\n";

  // Include policy
  if(file_exists('cms/config/policy.json')){
    $policy = json_decode(file_get_contents('cms/config/policy.json'), true);
    $context .= "POLICY:\n" . json_encode($policy, JSON_PRETTY_PRINT) . "\n\n";
  }

  // Include recent events (last 5)
  $events = glob('cms/events/*.json');
  rsort($events);
  $recentEvents = array_slice($events, 0, 5);
  $context .= "RECENT ACTIONS:\n";
  foreach($recentEvents as $file){
    $event = json_decode(file_get_contents($file), true);
    $context .= "- {$event['instruction']}\n";
  }
  $context .= "\n";

  // Include collection summaries
  $collections = glob('cms/collections/*', GLOB_ONLYDIR);
  $context .= "COLLECTIONS:\n";
  foreach($collections as $dir){
    $name = basename($dir);
    $count = count(glob("$dir/*.json"));
    $context .= "- $name ($count items)\n";
  }
  $context .= "\n";

  // Include current theme
  if(file_exists('cms/config/theme.json')){
    $theme = json_decode(file_get_contents('cms/config/theme.json'), true);
    $context .= "THEME:\n" . json_encode($theme, JSON_PRETTY_PRINT) . "\n\n";
  }

  // Include navigation
  if(file_exists('cms/config/navigation.json')){
    $nav = json_decode(file_get_contents('cms/config/navigation.json'), true);
    $context .= "NAVIGATION: " . json_encode($nav) . "\n\n";
  }

  // AI-driven section recommendations: analyze existing pages
  $context .= "CONTENT ANALYSIS:\n";
  $pagesDir = 'cms/collections/pages';
  if(is_dir($pagesDir)){
    $pageFiles = glob("{$pagesDir}/*.json");
    foreach($pageFiles as $file){
      $page = json_decode(file_get_contents($file), true);
      $basename = basename($file, '.json');

      if(isset($page['sections'])){
        $sectionTypes = array_map(function($s){ return $s['type'] ?? 'unknown'; }, $page['sections']);
        $context .= "- Page '{$basename}' has sections: " . implode(', ', $sectionTypes) . "\n";
      }
    }
  }
  $context .= "\n";

  $context .= "RECOMMENDATION INSTRUCTIONS:\n";
  $context .= "Analyze the current content and suggest new sections that would improve the site.\n";
  $context .= "Consider what's missing: testimonials, FAQ, team, pricing, contact, gallery, etc.\n";
  $context .= "Return suggestions in format: {\"section_suggestions\":[{\"section_type\":\"testimonials\",\"position\":\"after_hero\",\"reason\":\"Build trust with customer feedback\"}]}\n\n";

  $context .= "SCHEMA LOCATION (IMPORTANT):\n";
  $context .= "Schemas are co-located with collections: /cms/collections/{collection}/schema.json\n";
  $context .= "When creating a collection, always create schema.json in the collection directory.\n";
  $context .= "Example: create_collection 'testimonials' should create both:\n";
  $context .= "  - /cms/collections/testimonials/ (directory)\n";
  $context .= "  - /cms/collections/testimonials/schema.json (schema file)\n\n";

  $context .= "AVAILABLE PATCH OPERATIONS:\n";
  $context .= "1. create_collection - Create new collection with optional schema\n";
  $context .= "2. create_file - Create new file in collection or config\n";
  $context .= "3. update_file - Update existing file (supports merge)\n";
  $context .= "4. delete_file - Delete a file\n";
  $context .= "5. delete_collection - Remove entire collection\n";
  $context .= "6. update_schema - Update collection schema\n";
  $context .= "7. add_field_to_schema - Add single field to schema\n";
  $context .= "8. update_theme - Update theme configuration\n";
  $context .= "9. update_navigation - Update site navigation\n";
  $context .= "10. update_config - Update any config file\n";
  $context .= "11. create_collection_item - Shorthand for creating items (auto-IDs)\n\n";

  $context .= "OPERATION EXAMPLES:\n";
  $context .= "Create collection: {\"op\":\"create_collection\",\"target\":\"testimonials\",\"schema\":{\"fields\":{\"name\":\"string\",\"quote\":\"string\"}}}\n";
  $context .= "Add item: {\"op\":\"create_collection_item\",\"target\":\"posts\",\"value\":{\"title\":\"My Post\"}}\n";
  $context .= "Update file: {\"op\":\"update_file\",\"target\":\"collections/pages/home.json\",\"value\":{\"title\":\"New Title\"},\"merge\":true}\n";
  $context .= "Delete file: {\"op\":\"delete_file\",\"target\":\"collections/posts/old.json\"}\n\n";

  // Load collection starter templates
  $starterTemplates = file_exists('cms/templates/collection_starter.json')
    ? json_decode(file_get_contents('cms/templates/collection_starter.json'), true)
    : null;

  if($starterTemplates){
    $context .= "DYNAMIC COLLECTION CREATION:\n";
    $context .= "You can create new collections conversationally based on user requests.\n";
    $context .= "Use field type inference to determine appropriate types from field names:\n";

    foreach(($starterTemplates['field_type_inference'] ?? []) as $field => $type){
      $context .= "  - {$field}: {$type}\n";
    }

    $context .= "\nCommon collection templates available:\n";
    foreach(($starterTemplates['templates'] ?? []) as $name => $template){
      $fields = array_keys($template['schema']['fields'] ?? []);
      $context .= "  - {$name}: " . implode(', ', $fields) . "\n";
    }

    $context .= "\nWhen user requests a new collection:\n";
    $context .= "1. Infer appropriate schema from description or use template\n";
    $context .= "2. Create collection with create_collection operation\n";
    $context .= "3. Optionally create 1-2 example items with create_collection_item\n";
    $context .= "4. Confirm creation with user\n\n";

    $context .= "Example conversation:\n";
    $context .= "User: 'Add a testimonials section'\n";
    $context .= "AI: Creates testimonials collection with fields: name, company, quote, rating\n";
    $context .= "     Adds example testimonial item\n";
    $context .= "     Returns confirmation with form to add real testimonials\n\n";
  }

  return $context;
}

// Schema inference helper - infer field types from field names
function inferSchemaFromFields($fields){
  // Load field type inference rules
  $templates = file_exists('cms/templates/collection_starter.json')
    ? json_decode(file_get_contents('cms/templates/collection_starter.json'), true)
    : null;

  $inferenceRules = $templates['field_type_inference'] ?? [
    'name' => 'string',
    'title' => 'string',
    'description' => 'string',
    'content' => 'string',
    'price' => 'number',
    'rating' => 'number',
    'date' => 'string',
    'image' => 'string',
    'photo' => 'string',
    'active' => 'boolean',
    'featured' => 'boolean',
    'tags' => 'list',
    'features' => 'list'
  ];

  $schema = ['fields' => []];

  foreach($fields as $field){
    $fieldName = strtolower(trim($field));

    // Check exact match first
    if(isset($inferenceRules[$fieldName])){
      $schema['fields'][$fieldName] = $inferenceRules[$fieldName];
    }
    // Check for partial matches (e.g., "user_name" contains "name")
    else {
      $matched = false;
      foreach($inferenceRules as $pattern => $type){
        if(strpos($fieldName, $pattern) !== false){
          $schema['fields'][$fieldName] = $type;
          $matched = true;
          break;
        }
      }
      // Default to string if no match
      if(!$matched){
        $schema['fields'][$fieldName] = 'string';
      }
    }
  }

  return $schema;
}

// Get template schema for common collection types
function getCollectionTemplate($collectionType){
  $templates = file_exists('cms/templates/collection_starter.json')
    ? json_decode(file_get_contents('cms/templates/collection_starter.json'), true)
    : null;

  if(!$templates) return null;

  $type = strtolower(trim($collectionType));
  return $templates['templates'][$type] ?? null;
}

// Validation layer: validate data against schema
// Now supports co-located schemas (PRD spec)
function validateSchema($schemaName, $data, $collectionName = null){
  // Try collection-specific schema first
  if($collectionName){
    $colocatedPath = "cms/collections/{$collectionName}/schema.json";
    if(file_exists($colocatedPath)){
      $schema = json_decode(file_get_contents($colocatedPath), true);
      return validateSchemaFields($schema, $data);
    }
  }

  // Fall back to separate schemas directory
  $schemaPath = "cms/schemas/{$schemaName}.json";
  if(!file_exists($schemaPath)) return true; // No schema = no validation

  $schema = json_decode(file_get_contents($schemaPath), true);
  return validateSchemaFields($schema, $data);
}

// Extract validation logic for reuse
function validateSchemaFields($schema, $data){
  // Handle schema inheritance
  if(isset($schema['extends'])){
    $parentPath = "cms/schemas/{$schema['extends']}.json";
    if(file_exists($parentPath)){
      $parent = json_decode(file_get_contents($parentPath), true);
      $schema['fields'] = array_merge($parent['fields'] ?? [], $schema['fields'] ?? []);
    }
  }

  // Validate all required fields are present
  foreach(($schema['fields'] ?? []) as $f=>$type){
    if(!isset($data[$f])){
      throw new Exception("Missing required field: $f");
    }

    // Basic type validation
    $value = $data[$f];
    if($type == 'string' && !is_string($value)){
      throw new Exception("Field $f must be a string");
    }
    if($type == 'number' && !is_numeric($value)){
      throw new Exception("Field $f must be a number");
    }
    if($type == 'boolean' && !is_bool($value)){
      throw new Exception("Field $f must be a boolean");
    }
    if($type == 'list' && !is_array($value)){
      throw new Exception("Field $f must be an array");
    }
  }

  return true;
}

// Auto-generate forms for missing required schema fields
// Now supports co-located schemas
function validateAndGenerateForm($schemaName, $data, $collectionName = null){
  // Try collection-specific schema first
  if($collectionName){
    $colocatedPath = "cms/collections/{$collectionName}/schema.json";
    if(file_exists($colocatedPath)){
      $schema = json_decode(file_get_contents($colocatedPath), true);
      return generateFormFromSchema($schema, $data);
    }
  }

  // Fall back to separate schemas directory
  $schemaPath = "cms/schemas/{$schemaName}.json";
  if(!file_exists($schemaPath)) return null;

  $schema = json_decode(file_get_contents($schemaPath), true);
  return generateFormFromSchema($schema, $data);
}

// Extract form generation logic
function generateFormFromSchema($schema, $data){
  // Handle schema inheritance
  if(isset($schema['extends'])){
    $parentPath = "cms/schemas/{$schema['extends']}.json";
    if(file_exists($parentPath)){
      $parent = json_decode(file_get_contents($parentPath), true);
      $schema['fields'] = array_merge($parent['fields'] ?? [], $schema['fields'] ?? []);
    }
  }

  $form = ['fields'=>[]];

  // AI clarification with defaults: generate smart defaults based on context
  $defaults = [
    'title' => 'New Page',
    'name' => 'Untitled',
    'author' => 'Admin',
    'date' => date('Y-m-d'),
    'content' => '',
    'description' => '',
    'category' => 'General',
    'price' => '0.00',
    'inStock' => true
  ];

  // Check for missing fields
  foreach(($schema['fields'] ?? []) as $f=>$type){
    if(!isset($data[$f])){
      $field = [
        "name" => $f,
        "label" => ucfirst(str_replace('_', ' ', $f)),
        "type" => $type
      ];

      // Add AI-suggested default value if available
      if(isset($defaults[$f])){
        $field['default'] = $defaults[$f];
      }

      $form['fields'][] = $field;
    }
  }

  // Return form only if there are missing fields
  return count($form['fields']) > 0 ? $form : null;
}

function writeEvent($event){
  // Load previous hash from last event
  $events = glob('cms/events/*.json');
  rsort($events); // Get latest event first
  $prevHash = '';

  if(count($events) > 0){
    $lastEvent = json_decode(file_get_contents($events[0]), true);
    $prevHash = $lastEvent['hash'] ?? '';
  }

  $event['previous_hash'] = $prevHash;

  // Calculate hash from deterministic event data
  $hashData = [
    'id' => $event['id'],
    'timestamp' => $event['timestamp'],
    'actor' => $event['actor'],
    'instruction' => $event['instruction'],
    'patches' => $event['patches'],
    'previous_hash' => $prevHash
  ];
  $event['hash'] = hash('sha256', json_encode($hashData));

  // Sign the event (using temporary key for now)
  $key = sodium_crypto_sign_keypair();
  $event['signature'] = base64_encode(sodium_crypto_sign_detached(json_encode($event), $key));

  $id = $event['id'];
  file_put_contents("cms/events/$id.json", json_encode($event, JSON_PRETTY_PRINT));

  // Event audit log
  file_put_contents('cms/logs/audit.log', date('c').' '.$event['instruction']."\n", FILE_APPEND);
}

// Provider-specific API call functions
function callOpenAI($apiKey, $model, $systemPrompt, $userMessage){
  $payload = [
    "model" => $model,
    "messages" => [
      ["role" => "system", "content" => $systemPrompt],
      ["role" => "user", "content" => $userMessage]
    ]
  ];
  $ch = curl_init("https://api.openai.com/v1/chat/completions");
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $res = json_decode(curl_exec($ch), true);
  curl_close($ch);

  return $res['choices'][0]['message']['content'] ?? '';
}

function callClaude($apiKey, $model, $systemPrompt, $userMessage){
  $payload = [
    "model" => $model,
    "max_tokens" => 4096,
    "system" => $systemPrompt,
    "messages" => [
      ["role" => "user", "content" => $userMessage]
    ]
  ];
  $ch = curl_init("https://api.anthropic.com/v1/messages");
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-api-key: $apiKey",
    "anthropic-version: 2023-06-01",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $res = json_decode(curl_exec($ch), true);
  curl_close($ch);

  // Claude returns content in a different format
  return $res['content'][0]['text'] ?? '';
}

// Dynamic provider router
function callLLM($provider, $apiKey, $model, $systemPrompt, $userMessage){
  switch($provider){
    case 'openai':
      return callOpenAI($apiKey, $model, $systemPrompt, $userMessage);
    case 'claude':
      return callClaude($apiKey, $model, $systemPrompt, $userMessage);
    default:
      throw new Exception("Unsupported provider: $provider");
  }
}

$data = json_decode(file_get_contents('php://input'), true);

// Check if streaming is requested
$isStreaming = $data['stream'] ?? false;

// Handle confirmed requests (bypass confirmation check)
$isConfirmed = $data['confirmed'] ?? false;
$pendingEvent = $data['pending_event'] ?? null;

if($isConfirmed && $pendingEvent){
  // User approved the action, write the pending event
  writeEvent($pendingEvent);

  echo json_encode([
    'message' => 'Action approved and completed successfully.',
    'event' => $pendingEvent
  ]);
  exit;
}

// Build proactive memory context from event history
$contextPrompt = buildContext();

// Streaming response support
if($isStreaming){
  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache');
  header('X-Accel-Buffering: no'); // Disable nginx buffering

  // For streaming, we'll send the message first, then the structured response
  echo "data: " . json_encode(['type' => 'start']) . "\n\n";
  flush();

  // Call LLM and get response
  $aiMessage = callLLM($provider, $apiKey, $model, $contextPrompt, $data['message']);

  // Send response in chunks
  $chunks = str_split($aiMessage, 50);  // Split into 50-char chunks for streaming effect
  foreach($chunks as $chunk){
    echo "data: " . json_encode(['type' => 'chunk', 'content' => $chunk]) . "\n\n";
    flush();
    usleep(30000); // 30ms delay for visual streaming effect
  }

  echo "data: " . json_encode(['type' => 'end']) . "\n\n";
  flush();
  exit;
}

// Call the configured LLM provider (non-streaming)
$aiMessage = callLLM($provider, $apiKey, $model, $contextPrompt, $data['message']);
$response = json_decode($aiMessage, true);

// Validate response structure
if(!$response || !is_array($response)){
  echo json_encode([
    'message' => 'Error: AI returned invalid response format',
    'error' => 'Response must be valid JSON',
    'raw_response' => substr($aiMessage, 0, 500)
  ]);
  exit;
}

// Ensure required fields exist
if(!isset($response['message'])){
  $response['message'] = 'Action completed';
}

if(!isset($response['event'])){
  // Create minimal event structure if missing
  $response['event'] = [
    'id' => 'auto',
    'timestamp' => date('c'),
    'actor' => 'ai-agent',
    'instruction' => $data['message'],
    'patches' => []
  ];
}

// Auto-generate event ID and timestamp if not provided
if(!isset($response['event']['id']) || $response['event']['id'] === 'auto-generated' || $response['event']['id'] === 'auto'){
  $eventCount = count(glob('cms/events/*.json'));
  $response['event']['id'] = str_pad($eventCount + 1, 7, '0', STR_PAD_LEFT);
}

if(!isset($response['event']['timestamp']) || $response['event']['timestamp'] === 'ISO-8601' || $response['event']['timestamp'] === 'auto-generated'){
  $response['event']['timestamp'] = date('c');
}

// Ensure required event fields
if(!isset($response['event']['actor'])){
  $response['event']['actor'] = 'ai-agent';
}

if(!isset($response['event']['instruction'])){
  $response['event']['instruction'] = $data['message'];
}

if(!isset($response['event']['patches'])){
  $response['event']['patches'] = [];
}

// AI checks policy before applying patch (unless already confirmed)
if(!$isConfirmed){
  foreach($response['event']['patches'] ?? [] as $patch){
    if(in_array($patch['op'], $policy['require_confirmation_for'])){
      // Requires user confirmation
      $response['requires_confirmation'] = true;
      $response['pending_event'] = $response['event'];
      $response['confirmation_message'] = "You're about to perform a {$patch['op']} operation on {$patch['target']}. This action requires confirmation.";

      // Don't write event yet, wait for user confirmation
      echo json_encode([
        'message'=>$response['message'],
        'requires_confirmation'=>true,
        'pending_event'=>$response['event'],
        'confirmation_message'=>$response['confirmation_message']
      ]);
      exit;
    }
  }
}

// Validation layer: validate AI-generated data before writing
foreach($response['event']['patches'] ?? [] as $patch){
  if(($patch['op']=='create_file' || $patch['op']=='create_collection_item') && isset($patch['schema'])){
    try {
      validateSchema($patch['schema'], $patch['value']);
    } catch (Exception $e) {
      // Validation failed - generate form or return error
      $response['error'] = $e->getMessage();
      $generatedForm = validateAndGenerateForm($patch['schema'], $patch['value']);
      if($generatedForm){
        $response['form'] = array_merge($response['form'] ?? [], $generatedForm);
      }
      continue; // Skip writing this patch
    }
  }

  // Auto-generate forms for collection items with missing schema fields
  if($patch['op']=='create_file' && isset($patch['schema'])){
    $generatedForm = validateAndGenerateForm($patch['schema'], $patch['value']);
    if($generatedForm){
      $response['form'] = array_merge($response['form'] ?? [], $generatedForm);
    }
  }
}

// Persist dynamic forms to /cms/forms/ for audit trail
if(isset($response['form']) && $response['form'] !== null){
  $formId = $response['event']['id'];
  $formTimestamp = time();
  $formFilename = "form_{$formId}_{$formTimestamp}.json";

  $formData = [
    'event_id' => $response['event']['id'],
    'timestamp' => date('c'),
    'instruction' => $response['event']['instruction'],
    'form' => $response['form'],
    'submitted' => false
  ];

  file_put_contents("cms/forms/{$formFilename}", json_encode($formData, JSON_PRETTY_PRINT));
}

writeEvent($response['event']);

// Trigger replay to rebuild snapshots after event creation
exec('php replay.php > /dev/null 2>&1 &');  // Run in background

// Dynamic navigation: automatically update based on pages collection
$pagesDir = 'cms/collections/pages';
if(is_dir($pagesDir)){
  $nav = [];
  foreach(glob("{$pagesDir}/*.json") as $f){
    $page = json_decode(file_get_contents($f), true);
    if(isset($page['title'])){
      $nav[] = [
        'title' => $page['title'],
        'slug' => pathinfo($f, PATHINFO_FILENAME)
      ];
    }
  }
  file_put_contents('cms/config/navigation.json', json_encode($nav, JSON_PRETTY_PRINT));
}

if(in_array('add_to_navigation',$response['suggestions'])){
  $nav=json_decode(file_get_contents('cms/config/navigation.json'),true);
  $nav[]=$response['page'];
  file_put_contents('cms/config/navigation.json',json_encode($nav,JSON_PRETTY_PRINT));
}

// Proactive AI suggestions
if($policy['suggest_improvements']){
  $suggest=['Add SEO meta','Group navigation','Add footer'];
  $response['suggestions'] = array_merge($response['suggestions'] ?? [], $suggest);
}

// AI suggestion scoring: sort and rank suggestions
$scoredSuggestions = $response['scored_suggestions'] ?? [];
if(!empty($scoredSuggestions)){
  // Sort by score descending
  usort($scoredSuggestions, function($a, $b){
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
  });
  // Limit to top 5 suggestions
  $scoredSuggestions = array_slice($scoredSuggestions, 0, 5);
}

// AI-driven section recommendations
$sectionSuggestions = $response['section_suggestions'] ?? [];

echo json_encode([
  'message'=>$response['message'],
  'form'=>$response['form']??null,
  'suggestions'=>$response['suggestions']??[],
  'scored_suggestions'=>$scoredSuggestions,
  'section_suggestions'=>$sectionSuggestions,
  'error'=>$response['error']??null
]);

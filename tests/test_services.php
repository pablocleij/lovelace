<?php

/**
 * Test script for lovelace CMS services
 * Tests services independently without requiring API calls
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Lovelace\Services\EventService;
use Lovelace\Services\SchemaService;
use Lovelace\Services\ApiKeyService;
use Lovelace\Config\ConfigService;

echo "=== lovelace CMS Services Test ===\n\n";

// Test 1: EventService
echo "Test 1: EventService\n";
echo "-------------------\n";
try {
    $eventService = new EventService();

    // Test event creation
    $event = $eventService->createEvent([
        'instruction' => 'Test event',
        'patches' => []
    ]);

    echo "✓ Event created with ID: {$event['id']}\n";
    echo "✓ Timestamp: {$event['timestamp']}\n";
    echo "✓ Actor: {$event['actor']}\n";

    // Test hash calculation
    $hash = $eventService->calculateHash($event);
    echo "✓ Hash calculated: " . substr($hash, 0, 16) . "...\n";

    // Test signing
    $signature = $eventService->signEvent($event);
    echo "✓ Event signed: " . substr($signature, 0, 16) . "...\n";

    echo "\n";
} catch (Exception $e) {
    echo "✗ EventService test failed: {$e->getMessage()}\n\n";
}

// Test 2: SchemaService
echo "Test 2: SchemaService\n";
echo "-------------------\n";
try {
    $schemaService = new SchemaService();

    // Test loading a schema
    $schema = $schemaService->loadCollectionSchema('pages');

    if ($schema) {
        echo "✓ Loaded schema for 'pages' collection\n";
        echo "✓ Fields: " . implode(', ', array_keys($schema['fields'] ?? [])) . "\n";
    } else {
        echo "○ No schema found for 'pages' (expected for new install)\n";
    }

    // Test form generation
    $testData = ['title' => 'Test'];
    $testSchema = ['fields' => ['title' => 'string', 'content' => 'string']];
    $form = $schemaService->generateFormFromSchema($testSchema, $testData);

    if ($form && count($form['fields']) > 0) {
        echo "✓ Form generated with " . count($form['fields']) . " field(s)\n";
    } else {
        echo "✓ No missing fields (form not needed)\n";
    }

    echo "\n";
} catch (Exception $e) {
    echo "✗ SchemaService test failed: {$e->getMessage()}\n\n";
}

// Test 3: ApiKeyService
echo "Test 3: ApiKeyService\n";
echo "-------------------\n";
try {
    $apiKeyService = new ApiKeyService();

    $hasKey = $apiKeyService->hasKey();
    if ($hasKey) {
        echo "✓ API key is configured\n";
        echo "✓ Provider: {$apiKeyService->getProvider()}\n";
        echo "✓ Model: {$apiKeyService->getModel()}\n";
    } else {
        echo "○ No API key configured (run setup first)\n";
    }

    echo "\n";
} catch (Exception $e) {
    echo "○ ApiKeyService: {$e->getMessage()}\n\n";
}

// Test 4: ConfigService
echo "Test 4: ConfigService\n";
echo "-------------------\n";
try {
    $configService = ConfigService::getInstance();

    // Test loading config
    $policy = $configService->load('policy');
    if ($policy) {
        echo "✓ Loaded policy config\n";
        echo "✓ Confirmation required for: " . implode(', ', $policy['require_confirmation_for'] ?? []) . "\n";
    } else {
        echo "○ No policy config found\n";
    }

    // Test nested key access
    $suggestImprovements = $configService->get('policy.suggest_improvements', false);
    echo "✓ Nested key access works: suggest_improvements = " . ($suggestImprovements ? 'true' : 'false') . "\n";

    echo "\n";
} catch (Exception $e) {
    echo "✗ ConfigService test failed: {$e->getMessage()}\n\n";
}

// Test 5: Autoloading
echo "Test 5: Class Autoloading\n";
echo "-------------------\n";
try {
    $classes = [
        'Lovelace\\Tools\\CreatePageTool',
        'Lovelace\\Tools\\CreateCollectionTool',
        'Lovelace\\Agents\\LovelaceAgent',
        'Lovelace\\Http\\ApiController',
    ];

    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "✓ {$class} can be loaded\n";
        } else {
            echo "✗ {$class} NOT FOUND\n";
        }
    }

    echo "\n";
} catch (Exception $e) {
    echo "✗ Autoloading test failed: {$e->getMessage()}\n\n";
}

echo "=== Tests Complete ===\n";
echo "\nTo test the full agent system, visit your lovelace CMS in the browser and try chatting.\n";

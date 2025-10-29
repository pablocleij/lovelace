<?php

/**
 * lovelace CMS API Endpoint
 * Powered by Swis Agents SDK
 */

require_once __DIR__ . '/vendor/autoload.php';

use Lovelace\Http\ApiController;
use Lovelace\Services\EventService;
use Lovelace\Services\SchemaService;
use Lovelace\Services\ApiKeyService;

// Error handling
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

// Initialize services
$eventService = new EventService();
$schemaService = new SchemaService();
$apiKeyService = new ApiKeyService();

// Create controller and handle request
$controller = new ApiController($eventService, $schemaService, $apiKeyService);
$controller->handleRequest();

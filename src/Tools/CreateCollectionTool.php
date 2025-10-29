<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\Required;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\EventService;
use Lovelace\Services\SchemaService;

class CreateCollectionTool extends Tool
{
    #[ToolParameter('The name of the collection to create'), Required]
    public string $collectionName;

    #[ToolParameter('Schema definition with fields and types as an object'), Required]
    public object $schema;

    #[ToolParameter('Optional example item to add to the collection')]
    public ?object $exampleItem = null;

    protected ?string $toolDescription = 'Creates a new content collection with a schema (e.g., testimonials, products, team members)';

    public function __construct(
        private EventService $eventService,
        private SchemaService $schemaService
    ) {
    }

    public function __invoke(): ?string
    {
        try {
            $schemaArray = (array) $this->schema;

            // Create collection with schema
            $event = $this->eventService->createEvent([
                'instruction' => "Create collection: {$this->collectionName}",
                'patches' => [
                    [
                        'op' => 'create_collection',
                        'target' => $this->collectionName,
                        'value' => [
                            'schema' => $schemaArray
                        ]
                    ]
                ]
            ]);

            // Add example item if provided
            if ($this->exampleItem !== null) {
                $event['patches'][] = [
                    'op' => 'create_collection_item',
                    'target' => $this->collectionName,
                    'value' => (array) $this->exampleItem
                ];
            }

            // Write event
            $this->eventService->writeEvent($event);

            $message = "Successfully created collection '{$this->collectionName}' with schema.";
            if ($this->exampleItem !== null) {
                $message .= " Added example item.";
            }

            return $message;
        } catch (\Exception $e) {
            return "Error creating collection: " . $e->getMessage();
        }
    }
}

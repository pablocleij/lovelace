<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\Required;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\EventService;
use Lovelace\Services\SchemaService;

class CreatePageTool extends Tool
{
    #[ToolParameter('The page slug (URL-friendly identifier)'), Required]
    public string $slug;

    #[ToolParameter('The page title')]
    public string $title;

    #[ToolParameter('Array of section objects (each with type, title, content, etc.)', itemsType: 'object')]
    public array $sections = [];

    protected ?string $toolDescription = 'Creates a new page with sections for the website';

    public function __construct(
        private EventService $eventService,
        private SchemaService $schemaService
    ) {
    }

    public function __invoke(): ?string
    {
        try {
            // Build page data
            $pageData = [
                'slug' => $this->slug,
                'title' => $this->title,
                'sections' => $this->sections
            ];

            // Create event
            $event = $this->eventService->createEvent([
                'instruction' => "Create page: {$this->title}",
                'patches' => [
                    [
                        'op' => 'create_collection_item',
                        'target' => 'pages',
                        'value' => $pageData,
                        'schema' => 'page'
                    ]
                ]
            ]);

            // Write event
            $this->eventService->writeEvent($event);

            return "Successfully created page '{$this->title}' with " . count($this->sections) . " section(s).";
        } catch (\Exception $e) {
            return "Error creating page: " . $e->getMessage();
        }
    }
}

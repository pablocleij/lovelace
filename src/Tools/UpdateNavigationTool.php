<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\Required;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\EventService;

class UpdateNavigationTool extends Tool
{
    #[ToolParameter('Array of navigation items with title and slug', itemsType: 'object'), Required]
    public array $items;

    protected ?string $toolDescription = 'Updates the website navigation menu';

    public function __construct(private EventService $eventService)
    {
    }

    public function __invoke(): ?string
    {
        try {
            // Create navigation update event
            $event = $this->eventService->createEvent([
                'instruction' => "Update navigation menu",
                'patches' => [
                    [
                        'op' => 'update_navigation',
                        'target' => 'navigation',
                        'value' => $this->items
                    ]
                ]
            ]);

            $this->eventService->writeEvent($event);

            return "Successfully updated navigation menu with " . count($this->items) . " item(s).";
        } catch (\Exception $e) {
            return "Error updating navigation: " . $e->getMessage();
        }
    }
}

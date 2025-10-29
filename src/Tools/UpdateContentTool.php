<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\Required;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\EventService;

class UpdateContentTool extends Tool
{
    #[ToolParameter('The collection name (e.g., pages, posts)'), Required]
    public string $collection;

    #[ToolParameter('The item ID or slug to update'), Required]
    public string $itemId;

    #[ToolParameter('Updated data as an object'), Required]
    public object $data;

    protected ?string $toolDescription = 'Updates existing content in a collection';

    public function __construct(private EventService $eventService)
    {
    }

    public function __invoke(): ?string
    {
        try {
            $dataArray = (array) $this->data;

            // Create update event
            $event = $this->eventService->createEvent([
                'instruction' => "Update {$this->collection}/{$this->itemId}",
                'patches' => [
                    [
                        'op' => 'update_file',
                        'target' => "{$this->collection}/{$this->itemId}",
                        'value' => $dataArray
                    ]
                ]
            ]);

            $this->eventService->writeEvent($event);

            return "Successfully updated {$this->collection}/{$this->itemId}.";
        } catch (\Exception $e) {
            return "Error updating content: " . $e->getMessage();
        }
    }
}

<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\Required;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\EventService;

class DeleteContentTool extends Tool
{
    #[ToolParameter('The collection name'), Required]
    public string $collection;

    #[ToolParameter('The item ID or slug to delete'), Required]
    public string $itemId;

    #[ToolParameter('Confirmation flag - must be true to proceed'), Required]
    public bool $confirmed = false;

    protected ?string $toolDescription = 'Deletes content from a collection (requires confirmation)';

    public function __construct(private EventService $eventService)
    {
    }

    public function __invoke(): ?string
    {
        if (!$this->confirmed) {
            return json_encode([
                'requires_confirmation' => true,
                'message' => "Are you sure you want to delete {$this->collection}/{$this->itemId}? This action cannot be undone.",
                'action' => 'delete',
                'target' => "{$this->collection}/{$this->itemId}"
            ]);
        }

        try {
            // Create delete event
            $event = $this->eventService->createEvent([
                'instruction' => "Delete {$this->collection}/{$this->itemId}",
                'patches' => [
                    [
                        'op' => 'delete_file',
                        'target' => "{$this->collection}/{$this->itemId}"
                    ]
                ]
            ]);

            $this->eventService->writeEvent($event);

            return "Successfully deleted {$this->collection}/{$this->itemId}.";
        } catch (\Exception $e) {
            return "Error deleting content: " . $e->getMessage();
        }
    }
}

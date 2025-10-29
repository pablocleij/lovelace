<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\EventService;

class UpdateThemeTool extends Tool
{
    #[ToolParameter('Theme colors as an object with properties like primary, secondary, background')]
    public ?object $colors = null;

    #[ToolParameter('Theme fonts as an object with properties like heading, body')]
    public ?object $fonts = null;

    protected ?string $toolDescription = 'Updates the website theme configuration (colors and fonts)';

    public function __construct(private EventService $eventService)
    {
    }

    public function __invoke(): ?string
    {
        if ($this->colors === null && $this->fonts === null) {
            return "Error: Please provide either colors or fonts to update.";
        }

        try {
            $themeData = [];

            if ($this->colors !== null) {
                $themeData['colors'] = (array) $this->colors;
            }

            if ($this->fonts !== null) {
                $themeData['fonts'] = (array) $this->fonts;
            }

            // Create theme update event
            $event = $this->eventService->createEvent([
                'instruction' => "Update theme configuration",
                'patches' => [
                    [
                        'op' => 'update_theme',
                        'target' => 'theme',
                        'value' => $themeData
                    ]
                ]
            ]);

            $this->eventService->writeEvent($event);

            $updated = [];
            if ($this->colors !== null) $updated[] = 'colors';
            if ($this->fonts !== null) $updated[] = 'fonts';

            return "Successfully updated theme " . implode(' and ', $updated) . ".";
        } catch (\Exception $e) {
            return "Error updating theme: " . $e->getMessage();
        }
    }
}

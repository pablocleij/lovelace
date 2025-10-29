<?php

namespace Lovelace\Http;

use Swis\Agents\Orchestrator;
use Swis\Agents\Agent;
use Lovelace\Agents\LovelaceAgent;
use Lovelace\Services\EventService;
use Lovelace\Services\SchemaService;
use Lovelace\Services\SnapshotService;
use Lovelace\Services\ApiKeyService;

class ApiController
{
    private Orchestrator $orchestrator;
    private Agent $mainAgent;
    private SnapshotService $snapshotService;

    public function __construct(
        private EventService $eventService,
        private SchemaService $schemaService,
        private ApiKeyService $apiKeyService
    ) {
        $this->orchestrator = new Orchestrator();
        $this->mainAgent = LovelaceAgent::create($eventService, $schemaService);
        $this->snapshotService = new SnapshotService($eventService);
    }

    public function handleRequest(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $message = $input['message'] ?? '';
            $isStreaming = $input['stream'] ?? false;
            $isConfirmed = $input['confirmed'] ?? false;

            if (empty($message)) {
                $this->sendJson(['error' => true, 'message' => 'Message is required']);
                return;
            }

            // Check API key
            if (!$this->apiKeyService->hasKey()) {
                $this->sendJson([
                    'error' => true,
                    'message' => 'API key not configured. Please click the 🔑 API button to set up your API key.'
                ]);
                return;
            }

            // Configure the orchestrator with API key
            $apiKey = $this->apiKeyService->getApiKey();
            $provider = $this->apiKeyService->getProvider();

            // Set OpenAI API key for the SDK
            if ($provider === 'openai') {
                putenv("OPENAI_API_KEY={$apiKey}");
            }

            $this->orchestrator->withUserInstruction($message);

            if ($isStreaming) {
                $this->handleStreaming();
            } else {
                $this->handleNonStreaming();
            }
        } catch (\Exception $e) {
            http_response_code(500);
            $this->sendJson([
                'error' => true,
                'message' => $e->getMessage(),
                'details' => $e->getFile() . ':' . $e->getLine()
            ]);
        }
    }

    private function handleStreaming(): void
    {
        // Disable buffering for real-time streaming
        while (ob_get_level()) ob_end_clean();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }

        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', false);

        echo "data: " . json_encode(['type' => 'start']) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();

        try {
            // Stream the response
            $this->orchestrator->runStreamed($this->mainAgent, function ($token) {
                echo "data: " . json_encode(['type' => 'chunk', 'content' => $token]) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            });

            echo "data: " . json_encode(['type' => 'end']) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            // After streaming, rebuild snapshot if needed
            $this->rebuildSnapshotIfNeeded();
        } catch (\Exception $e) {
            echo "data: " . json_encode(['type' => 'error', 'content' => $e->getMessage()]) . "\n\n";
            flush();
        }
    }

    private function handleNonStreaming(): void
    {
        try {
            // Run the agent
            $response = $this->orchestrator->run($this->mainAgent);

            // Rebuild snapshot after agent completes
            $this->rebuildSnapshotIfNeeded();

            // Return structured response
            $this->sendJson([
                'message' => $response,
                'form' => null,
                'suggestions' => [],
                'scored_suggestions' => $this->generateSuggestions(),
                'section_suggestions' => [],
                'error' => null
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function rebuildSnapshotIfNeeded(): void
    {
        // Check if any new events were created
        $latestEvent = $this->eventService->getLatestEvent();

        if ($latestEvent) {
            // Rebuild snapshot
            $this->snapshotService->rebuild();
        }
    }

    private function generateSuggestions(): array
    {
        // Generate smart suggestions based on current site state
        $suggestions = [
            [
                'suggestion' => 'Add an about page',
                'score' => 0.85,
                'reason' => 'Help visitors learn more about you or your business'
            ],
            [
                'suggestion' => 'Create a contact section',
                'score' => 0.90,
                'reason' => 'Make it easy for visitors to get in touch'
            ],
            [
                'suggestion' => 'Customize your theme colors',
                'score' => 0.75,
                'reason' => 'Match your brand identity'
            ]
        ];

        return $suggestions;
    }

    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

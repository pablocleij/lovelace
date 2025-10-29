<?php

namespace Lovelace\Services;

class EventService
{
    private string $eventsDir = 'cms/events';
    private string $auditLog = 'cms/logs/audit.log';

    public function createEvent(array $eventData): array
    {
        // Auto-generate event ID if not provided
        if (!isset($eventData['id']) || $eventData['id'] === 'auto-generated' || $eventData['id'] === 'auto') {
            $eventCount = count(glob($this->eventsDir . '/*.json'));
            $eventData['id'] = str_pad($eventCount + 1, 7, '0', STR_PAD_LEFT);
        }

        // Auto-generate timestamp if not provided
        if (!isset($eventData['timestamp']) || $eventData['timestamp'] === 'ISO-8601' || $eventData['timestamp'] === 'auto-generated') {
            $eventData['timestamp'] = date('c');
        }

        // Ensure required fields
        if (!isset($eventData['actor'])) {
            $eventData['actor'] = 'ai-agent';
        }

        if (!isset($eventData['patches'])) {
            $eventData['patches'] = [];
        }

        return $eventData;
    }

    public function writeEvent(array $event): void
    {
        // Load previous hash from last event
        $events = glob($this->eventsDir . '/*.json');
        rsort($events); // Get latest event first
        $prevHash = '';

        if (count($events) > 0) {
            $lastEvent = json_decode(file_get_contents($events[0]), true);
            $prevHash = $lastEvent['hash'] ?? '';
        }

        $event['previous_hash'] = $prevHash;

        // Calculate hash from deterministic event data
        $event['hash'] = $this->calculateHash($event);

        // Sign the event
        $event['signature'] = $this->signEvent($event);

        $id = $event['id'];
        file_put_contents("{$this->eventsDir}/$id.json", json_encode($event, JSON_PRETTY_PRINT));

        // Event audit log
        file_put_contents($this->auditLog, date('c') . ' ' . $event['instruction'] . "\n", FILE_APPEND);
    }

    public function getLatestEvent(): ?array
    {
        $events = glob($this->eventsDir . '/*.json');
        if (empty($events)) {
            return null;
        }

        rsort($events);
        return json_decode(file_get_contents($events[0]), true);
    }

    public function calculateHash(array $event): string
    {
        $hashData = [
            'id' => $event['id'],
            'timestamp' => $event['timestamp'],
            'actor' => $event['actor'],
            'instruction' => $event['instruction'],
            'patches' => $event['patches'],
            'previous_hash' => $event['previous_hash'] ?? ''
        ];

        return hash('sha256', json_encode($hashData));
    }

    public function signEvent(array $event): string
    {
        // Use HMAC-SHA256 (no extension required)
        $secretKey = 'lovelace-event-signing-key-' . ($_ENV['EVENT_SECRET'] ?? 'default-secret');
        return hash_hmac('sha256', json_encode($event), $secretKey);
    }
}

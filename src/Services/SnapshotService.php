<?php

namespace Lovelace\Services;

class SnapshotService
{
    private string $snapshotsDir = 'cms/snapshots';
    private string $replayScript = 'replay.php';

    public function __construct(private EventService $eventService)
    {
    }

    public function rebuild(): void
    {
        // Trigger replay.php to rebuild snapshot
        // Use include instead of exec for cross-platform compatibility
        ob_start();
        include $this->replayScript;
        ob_end_clean();
    }

    public function getLatest(): array
    {
        $snapshotPath = "{$this->snapshotsDir}/latest.json";

        if (!file_exists($snapshotPath)) {
            // Initialize if doesn't exist
            $this->rebuild();
        }

        if (!file_exists($snapshotPath)) {
            // Still doesn't exist, return empty
            return [];
        }

        return json_decode(file_get_contents($snapshotPath), true) ?? [];
    }

    public function applyPatch(array $snapshot, array $patch): array
    {
        // This method applies a single patch operation to the snapshot
        // The actual implementation matches the logic in replay.php
        $op = $patch['op'];
        $target = $patch['target'] ?? '';
        $value = $patch['value'] ?? null;

        switch ($op) {
            case 'create_file':
            case 'update_file':
            case 'create_collection_item':
                // Add or update item in snapshot
                $snapshot[] = $value;
                break;

            case 'delete_file':
                // Remove item from snapshot
                // This would need more sophisticated logic based on target
                break;

            case 'create_collection':
                // Collection metadata
                if (isset($value['schema'])) {
                    $snapshot[] = $value['schema'];
                }
                break;

            case 'update_schema':
                // Update schema in snapshot
                $snapshot[] = $value;
                break;

            case 'update_theme':
                // Update theme in snapshot
                if (isset($value)) {
                    $snapshot[] = $value;
                }
                break;

            case 'update_navigation':
                // Update navigation in snapshot
                if (isset($value)) {
                    $snapshot[] = $value;
                }
                break;

            case 'update_config':
                // Update config in snapshot
                if (isset($value)) {
                    $snapshot[] = $value;
                }
                break;
        }

        return $snapshot;
    }
}

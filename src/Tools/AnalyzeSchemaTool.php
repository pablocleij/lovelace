<?php

namespace Lovelace\Tools;

use Swis\Agents\Tool;
use Swis\Agents\Tool\Required;
use Swis\Agents\Tool\ToolParameter;
use Lovelace\Services\SchemaService;

class AnalyzeSchemaTool extends Tool
{
    #[ToolParameter('The collection name to analyze'), Required]
    public string $collection;

    protected ?string $toolDescription = 'Analyzes existing content in a collection to infer or improve schemas';

    public function __construct(private SchemaService $schemaService)
    {
    }

    public function __invoke(): ?string
    {
        try {
            $collectionPath = "cms/collections/{$this->collection}";

            if (!is_dir($collectionPath)) {
                return "Error: Collection '{$this->collection}' does not exist.";
            }

            // Get all items in collection
            $items = glob("{$collectionPath}/*.json");

            if (empty($items)) {
                return "Collection '{$this->collection}' is empty. No items to analyze.";
            }

            // Analyze items to infer schema
            $inferredFields = [];
            foreach ($items as $itemFile) {
                $item = json_decode(file_get_contents($itemFile), true);
                if ($item) {
                    foreach ($item as $key => $value) {
                        $type = $this->inferType($value);
                        if (!isset($inferredFields[$key])) {
                            $inferredFields[$key] = $type;
                        }
                    }
                }
            }

            // Check if schema already exists
            $existingSchema = $this->schemaService->loadCollectionSchema($this->collection);

            if ($existingSchema) {
                // Compare with existing
                $missing = array_diff_key($inferredFields, $existingSchema['fields'] ?? []);
                if (!empty($missing)) {
                    $fieldList = implode(', ', array_keys($missing));
                    return "Collection '{$this->collection}' has a schema but is missing fields: {$fieldList}. Inferred types: " . json_encode($missing);
                } else {
                    return "Collection '{$this->collection}' schema is up to date with all content fields.";
                }
            } else {
                // No schema exists, suggest creating one
                return "Collection '{$this->collection}' has no schema. Analyzed " . count($items) . " item(s) and inferred these fields: " . json_encode($inferredFields, JSON_PRETTY_PRINT);
            }
        } catch (\Exception $e) {
            return "Error analyzing schema: " . $e->getMessage();
        }
    }

    private function inferType(mixed $value): string
    {
        if (is_string($value)) return 'string';
        if (is_int($value) || is_float($value)) return 'number';
        if (is_bool($value)) return 'boolean';
        if (is_array($value)) return 'list';
        if (is_object($value)) return 'object';
        return 'string';
    }
}

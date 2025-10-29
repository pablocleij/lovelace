<?php

namespace Lovelace\Services;

class SchemaService
{
    private string $schemasDir = 'cms/schemas';
    private string $collectionsDir = 'cms/collections';

    public function loadCollectionSchema(string $collection): ?array
    {
        // Try collection-specific schema first (co-located)
        $colocatedPath = "{$this->collectionsDir}/{$collection}/schema.json";
        if (file_exists($colocatedPath)) {
            return json_decode(file_get_contents($colocatedPath), true);
        }

        // Fall back to separate schemas directory
        $schemaPath = "{$this->schemasDir}/{$collection}.json";
        if (file_exists($schemaPath)) {
            return json_decode(file_get_contents($schemaPath), true);
        }

        return null;
    }

    public function mergeSchema(array $schema): array
    {
        // Handle schema inheritance
        if (isset($schema['extends'])) {
            $parentPath = "{$this->schemasDir}/{$schema['extends']}.json";
            if (file_exists($parentPath)) {
                $parent = json_decode(file_get_contents($parentPath), true);
                $schema['fields'] = array_merge($parent['fields'] ?? [], $schema['fields'] ?? []);
            }
        }

        return $schema;
    }

    public function validateSchema(string $schemaName, array $data, ?string $collectionName = null): void
    {
        // Try collection-specific schema first
        if ($collectionName) {
            $colocatedPath = "{$this->collectionsDir}/{$collectionName}/schema.json";
            if (file_exists($colocatedPath)) {
                $schema = json_decode(file_get_contents($colocatedPath), true);
                $this->validateSchemaFields($schema, $data);
                return;
            }
        }

        // Fall back to separate schemas directory
        $schemaPath = "{$this->schemasDir}/{$schemaName}.json";
        if (!file_exists($schemaPath)) {
            return; // No schema = no validation
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        $this->validateSchemaFields($schema, $data);
    }

    private function validateSchemaFields(array $schema, array $data): void
    {
        // Merge schema with inheritance
        $schema = $this->mergeSchema($schema);

        // Validate all required fields are present
        foreach (($schema['fields'] ?? []) as $field => $type) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }

            // Basic type validation
            $value = $data[$field];
            if ($type == 'string' && !is_string($value)) {
                throw new \Exception("Field $field must be a string");
            }
            if ($type == 'number' && !is_numeric($value)) {
                throw new \Exception("Field $field must be a number");
            }
            if ($type == 'boolean' && !is_bool($value)) {
                throw new \Exception("Field $field must be a boolean");
            }
            if ($type == 'list' && !is_array($value)) {
                throw new \Exception("Field $field must be an array");
            }
        }
    }

    public function generateFormFromSchema(array $schema, array $data): ?array
    {
        // Merge schema with inheritance
        $schema = $this->mergeSchema($schema);

        $form = ['fields' => []];

        // Smart defaults based on field names
        $defaults = [
            'title' => 'New Page',
            'name' => 'Untitled',
            'author' => 'Admin',
            'date' => date('Y-m-d'),
            'content' => '',
            'description' => '',
            'category' => 'General',
            'price' => '0.00',
            'inStock' => true
        ];

        // Check for missing fields
        foreach (($schema['fields'] ?? []) as $field => $type) {
            if (!isset($data[$field])) {
                $formField = [
                    "name" => $field,
                    "label" => ucfirst(str_replace('_', ' ', $field)),
                    "type" => $type
                ];

                // Add AI-suggested default value if available
                if (isset($defaults[$field])) {
                    $formField['default'] = $defaults[$field];
                }

                $form['fields'][] = $formField;
            }
        }

        // Return form only if there are missing fields
        return count($form['fields']) > 0 ? $form : null;
    }

    public function validateAndGenerateForm(string $schemaName, array $data, ?string $collectionName = null): ?array
    {
        // Try collection-specific schema first
        if ($collectionName) {
            $colocatedPath = "{$this->collectionsDir}/{$collectionName}/schema.json";
            if (file_exists($colocatedPath)) {
                $schema = json_decode(file_get_contents($colocatedPath), true);
                return $this->generateFormFromSchema($schema, $data);
            }
        }

        // Fall back to separate schemas directory
        $schemaPath = "{$this->schemasDir}/{$schemaName}.json";
        if (!file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        return $this->generateFormFromSchema($schema, $data);
    }
}

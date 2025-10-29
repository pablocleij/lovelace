<?php

namespace Lovelace\Config;

class ConfigService
{
    private static ?self $instance = null;
    private array $config = [];
    private string $configDir = 'cms/config';

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function load(string $key): mixed
    {
        if (!isset($this->config[$key])) {
            $path = "{$this->configDir}/{$key}.json";
            if (file_exists($path)) {
                $this->config[$key] = json_decode(file_get_contents($path), true);
            } else {
                $this->config[$key] = null;
            }
        }
        return $this->config[$key];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Support nested keys with dot notation (e.g., 'theme.colors.primary')
        $keys = explode('.', $key);
        $configKey = array_shift($keys);

        $data = $this->load($configKey);

        if ($data === null) {
            return $default;
        }

        // Navigate nested structure
        foreach ($keys as $nestedKey) {
            if (!isset($data[$nestedKey])) {
                return $default;
            }
            $data = $data[$nestedKey];
        }

        return $data;
    }

    public function save(string $key, array $data): void
    {
        $path = "{$this->configDir}/{$key}.json";

        // Ensure directory exists
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0777, true);
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        // Update cache
        $this->config[$key] = $data;
    }

    public function reload(string $key): void
    {
        unset($this->config[$key]);
        $this->load($key);
    }

    public function all(): array
    {
        return $this->config;
    }
}

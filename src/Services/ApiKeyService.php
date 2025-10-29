<?php

namespace Lovelace\Services;

class ApiKeyService
{
    private string $configFile = 'cms/config/api_key.json';

    public function getApiKey(): string
    {
        $config = $this->loadConfig();

        if (empty($config['key']) || $config['key'] === 'YOUR_KEY_HERE') {
            throw new \Exception('API key not configured');
        }

        $isEncrypted = $config['encrypted'] ?? false;
        $key = $isEncrypted ? $this->decryptKey($config['key']) : $config['key'];

        if (!$key) {
            throw new \Exception('Failed to decrypt API key');
        }

        return $key;
    }

    public function getProvider(): string
    {
        $config = $this->loadConfig();
        return $config['provider'] ?? 'openai';
    }

    public function getModel(): string
    {
        $config = $this->loadConfig();
        return $config['model'] ?? 'gpt-4o';
    }

    public function hasKey(): bool
    {
        if (!file_exists($this->configFile)) {
            return false;
        }

        $config = $this->loadConfig();
        $key = $config['key'] ?? '';

        return !empty($key) && $key !== 'YOUR_KEY_HERE' && strlen($key) >= 10;
    }

    public function encryptAndSave(string $key, string $provider, string $model): void
    {
        // Validate key format
        if ($provider === 'openai' && !preg_match('/^sk-[A-Za-z0-9\-_]{20,}/', $key)) {
            throw new \Exception('Invalid OpenAI API key format');
        }

        if ($provider === 'claude' && !preg_match('/^sk-ant-[A-Za-z0-9\-]+/', $key)) {
            throw new \Exception('Invalid Anthropic API key format');
        }

        $encryptedKey = $this->encryptKey($key);

        $config = [
            'provider' => $provider,
            'key' => $encryptedKey,
            'model' => $model,
            'encrypted' => true,
            'last_updated' => date('c')
        ];

        file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT));
    }

    public function validate(): bool
    {
        if (!function_exists('curl_init')) {
            // CURL not available, skip validation
            return true;
        }

        try {
            $apiKey = $this->getApiKey();
            $provider = $this->getProvider();

            if ($provider === 'openai') {
                return $this->validateOpenAI($apiKey);
            } elseif ($provider === 'claude') {
                return $this->validateClaude($apiKey);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function validateOpenAI(string $apiKey): bool
    {
        $ch = curl_init("https://api.openai.com/v1/models");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    private function validateClaude(string $apiKey): bool
    {
        $ch = curl_init("https://api.anthropic.com/v1/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-api-key: $apiKey",
            "anthropic-version: 2023-06-01",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'test']]
        ]));
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    private function loadConfig(): array
    {
        if (!file_exists($this->configFile)) {
            return [];
        }

        return json_decode(file_get_contents($this->configFile), true) ?? [];
    }

    private function encryptKey(string $key): string
    {
        return base64_encode('SIMPLE::' . $key);
    }

    private function decryptKey(string $encryptedKey): ?string
    {
        $decoded = base64_decode($encryptedKey);
        if (strpos($decoded, 'SIMPLE::') === 0) {
            return substr($decoded, 8);
        }
        return null;
    }
}

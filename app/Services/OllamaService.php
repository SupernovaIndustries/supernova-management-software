<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OllamaService
 *
 * Service for interacting with Ollama AI API (local AI models).
 * Provides methods to send prompts and receive AI-generated responses.
 */
class OllamaService
{
    /**
     * Default Ollama API URL
     */
    protected string $defaultUrl = 'http://localhost:11434';

    /**
     * Default model to use
     */
    protected string $defaultModel = 'llama3.2';

    /**
     * Request timeout in seconds
     */
    protected int $timeout = 60;

    /**
     * Get the configured Ollama URL from CompanyProfile
     *
     * @return string
     */
    protected function getOllamaUrl(): string
    {
        $profile = CompanyProfile::current();
        return $profile->ollama_url ?? $this->defaultUrl;
    }

    /**
     * Get the configured Ollama model from CompanyProfile
     *
     * @return string
     */
    protected function getOllamaModel(): string
    {
        $profile = CompanyProfile::current();
        return $profile->ollama_model ?? $this->defaultModel;
    }

    /**
     * Check if Ollama is available and responding
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $url = $this->getOllamaUrl();
            $response = Http::timeout(5)->get("{$url}/api/tags");

            return $response->successful();
        } catch (\Exception $e) {
            Log::debug('Ollama availability check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate a completion using Ollama API
     *
     * @param string $prompt The prompt to send to the AI
     * @param array $options Additional options for the request
     * @return array|null Response data or null on failure
     */
    public function generate(string $prompt, array $options = []): ?array
    {
        try {
            $url = $this->getOllamaUrl();
            $model = $options['model'] ?? $this->getOllamaModel();

            Log::info('Ollama API request', [
                'url' => $url,
                'model' => $model,
                'prompt_length' => strlen($prompt)
            ]);

            $payload = [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ];

            // Only add options if they exist and are not empty
            if (!empty($options['generation_options'])) {
                $payload['options'] = (object) $options['generation_options'];
            }

            $response = Http::timeout($this->timeout)
                ->post("{$url}/api/generate", $payload);

            if (!$response->successful()) {
                Log::error('Ollama API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            Log::info('Ollama API response received', [
                'model' => $model,
                'response_length' => strlen($data['response'] ?? '')
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Ollama API exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Generate a completion and return only the response text
     *
     * @param string $prompt The prompt to send to the AI
     * @param array $options Additional options for the request
     * @return string|null Response text or null on failure
     */
    public function generateText(string $prompt, array $options = []): ?string
    {
        $result = $this->generate($prompt, $options);
        return $result['response'] ?? null;
    }

    /**
     * Generate a JSON response using Ollama API
     * Instructs the model to respond in JSON format and parses the result
     *
     * @param string $prompt The prompt to send to the AI
     * @param array $options Additional options for the request
     * @return array|null Parsed JSON response or null on failure
     */
    public function generateJson(string $prompt, array $options = []): ?array
    {
        // Add JSON format instruction to prompt
        $jsonPrompt = $prompt . "\n\nIMPORTANT: Respond ONLY with valid JSON. Do not include any explanatory text before or after the JSON.";

        $result = $this->generate($jsonPrompt, $options);

        if (!$result || !isset($result['response'])) {
            return null;
        }

        $responseText = trim($result['response']);

        // Try to extract JSON from the response
        $json = $this->extractJson($responseText);

        if ($json === null) {
            Log::warning('Failed to parse JSON from Ollama response', [
                'response' => substr($responseText, 0, 500)
            ]);
        }

        return $json;
    }

    /**
     * Extract JSON from a text response
     * Handles cases where the AI includes extra text around the JSON
     *
     * @param string $text Text potentially containing JSON
     * @return array|null Parsed JSON or null on failure
     */
    protected function extractJson(string $text): ?array
    {
        // First try to decode the entire text
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try to find JSON object in the text
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Try to find JSON array in the text
        if (preg_match('/\[[^\[\]]*(?:\[[^\[\]]*\][^\[\]]*)*\]/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Chat with Ollama using conversation context
     *
     * @param array $messages Array of messages in format ['role' => 'user/assistant', 'content' => 'text']
     * @param array $options Additional options for the request
     * @return array|null Response data or null on failure
     */
    public function chat(array $messages, array $options = []): ?array
    {
        try {
            $url = $this->getOllamaUrl();
            $model = $options['model'] ?? $this->getOllamaModel();

            Log::info('Ollama chat request', [
                'url' => $url,
                'model' => $model,
                'message_count' => count($messages)
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$url}/api/chat", [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => $options['generation_options'] ?? []
                ]);

            if (!$response->successful()) {
                Log::error('Ollama chat request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama chat exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * List available models on the Ollama instance
     *
     * @return array List of available models
     */
    public function listModels(): array
    {
        try {
            $url = $this->getOllamaUrl();
            $response = Http::timeout(10)->get("{$url}/api/tags");

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            return $data['models'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list Ollama models', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get information about a specific model
     *
     * @param string|null $modelName Model name (uses configured model if null)
     * @return array|null Model information or null on failure
     */
    public function getModelInfo(?string $modelName = null): ?array
    {
        try {
            $url = $this->getOllamaUrl();
            $model = $modelName ?? $this->getOllamaModel();

            $response = Http::timeout(10)->post("{$url}/api/show", [
                'name' => $model
            ]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to get Ollama model info', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

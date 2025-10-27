<?php

namespace App\Services;

use App\Contracts\AiServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Factory for creating AI service instances.
 *
 * This factory determines which AI provider to use based on configuration.
 * Supports multiple AI providers with fallback mechanism.
 */
class AiServiceFactory
{
    /**
     * Create an AI service instance.
     *
     * @return AiServiceInterface
     */
    public static function make(): AiServiceInterface
    {
        $provider = config('services.ai.provider', 'claude');

        Log::debug('AiServiceFactory creating service', ['provider' => $provider]);

        return match ($provider) {
            'ollama' => new OllamaAiService(),
            'claude' => new ClaudeAiService(),
            default => self::createDefaultService(),
        };
    }

    /**
     * Create default AI service with fallback logic.
     *
     * Tries providers in order of preference:
     * 1. Claude AI (if configured)
     * 2. Ollama (if running locally)
     * 3. Claude AI (fallback, will return empty results if not configured)
     *
     * @return AiServiceInterface
     */
    private static function createDefaultService(): AiServiceInterface
    {
        // Try Claude first (most reliable for production)
        $claudeService = new ClaudeAiService();
        if ($claudeService->isConfigured()) {
            Log::debug('Using Claude AI service (auto-detected)');
            return $claudeService;
        }

        // Try Ollama as fallback (local AI)
        $ollamaService = new OllamaAiService();
        if ($ollamaService->isConfigured()) {
            Log::debug('Using Ollama AI service (auto-detected)');
            return $ollamaService;
        }

        // Return Claude as final fallback (will show warnings if not configured)
        Log::debug('No AI service configured, returning Claude AI (unconfigured)');
        return $claudeService;
    }

    /**
     * Get all available AI providers.
     *
     * @return array
     */
    public static function getAvailableProviders(): array
    {
        $providers = [];

        $claudeService = new ClaudeAiService();
        if ($claudeService->isConfigured()) {
            $providers[] = [
                'name' => 'claude',
                'label' => 'Claude AI (Anthropic)',
                'configured' => true,
            ];
        }

        $ollamaService = new OllamaAiService();
        if ($ollamaService->isConfigured()) {
            $providers[] = [
                'name' => 'ollama',
                'label' => 'Ollama (Local AI)',
                'configured' => true,
            ];
        }

        return $providers;
    }

    /**
     * Check if any AI service is available.
     *
     * @return bool
     */
    public static function hasAvailableService(): bool
    {
        return !empty(self::getAvailableProviders());
    }
}

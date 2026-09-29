<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Reads the extension configuration once and resolves provider, key, model
 * and limits. Single place for the default model IDs.
 */
class AiSettingsFactory
{
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_OLLAMA = 'ollama';

    public const DEFAULT_ANTHROPIC_MODEL = 'claude-sonnet-5-5';
    public const DEFAULT_OPENAI_MODEL = 'gpt-4o-mini';
    public const DEFAULT_OLLAMA_MODEL = 'llama3.2';
    public const DEFAULT_MAX_TOKENS = 16000;

    private const EFFORT_LEVELS = ['low', 'medium', 'high'];

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function create(): AiSettings
    {
        $config = $this->getExtensionConfig();
        $provider = (string)($config['aiProvider'] ?? self::PROVIDER_ANTHROPIC);

        [$apiKey, $model] = match ($provider) {
            self::PROVIDER_OPENAI => [
                (string)($config['openaiApiKey'] ?? ''),
                (string)($config['openaiModel'] ?? '') ?: self::DEFAULT_OPENAI_MODEL,
            ],
            self::PROVIDER_OLLAMA => [
                '',
                (string)($config['ollamaModel'] ?? '') ?: self::DEFAULT_OLLAMA_MODEL,
            ],
            default => [
                (string)($config['anthropicApiKey'] ?? ''),
                (string)($config['anthropicModel'] ?? '') ?: self::DEFAULT_ANTHROPIC_MODEL,
            ],
        };
        if (!in_array($provider, [self::PROVIDER_OPENAI, self::PROVIDER_OLLAMA], true)) {
            $provider = self::PROVIDER_ANTHROPIC;
        }

        $maxTokens = (int)($config['maxTokens'] ?? 0);
        $effort = (string)($config['anthropicEffort'] ?? 'low');

        return new AiSettings(
            provider: $provider,
            apiKey: trim($apiKey),
            model: trim($model),
            maxTokens: $maxTokens > 0 ? $maxTokens : self::DEFAULT_MAX_TOKENS,
            analysisEnabled: !empty($config['enableAiAnalysis'] ?? '1'),
            anthropicEffort: in_array($effort, self::EFFORT_LEVELS, true) ? $effort : '',
            anthropicFallback: !empty($config['anthropicFallback'] ?? '1'),
        );
    }

    private function getExtensionConfig(): array
    {
        try {
            $config = $this->extensionConfiguration->get('t3_content_quality');
            return is_array($config) ? $config : [];
        } catch (\Throwable) {
            return [];
        }
    }
}

<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

/**
 * Resolved AI provider settings for one request, built by AiSettingsFactory.
 */
final class AiSettings
{
    public function __construct(
        public readonly string $provider,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly int $maxTokens,
        public readonly bool $analysisEnabled = true,
        public readonly string $anthropicEffort = '',
        public readonly bool $anthropicFallback = false,
    ) {}

    /** A provider is usable when it has an API key, or is Ollama (no key needed). */
    public function isUsable(): bool
    {
        return $this->provider === AiSettingsFactory::PROVIDER_OLLAMA || $this->apiKey !== '';
    }
}

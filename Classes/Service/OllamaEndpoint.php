<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Resolves the Ollama server address from the extension configuration
 * (setting "ollamaUrl"), defaulting to the Docker/DDEV service name.
 */
class OllamaEndpoint
{
    public const DEFAULT_BASE_URL = 'http://ollama:11434';

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function baseUrl(): string
    {
        try {
            $url = trim((string)($this->extensionConfiguration->get('t3_content_quality', 'ollamaUrl') ?? ''));
        } catch (\Throwable) {
            $url = '';
        }

        return rtrim($url !== '' ? $url : self::DEFAULT_BASE_URL, '/');
    }

    public function chatUrl(): string
    {
        return $this->baseUrl() . '/api/chat';
    }
}

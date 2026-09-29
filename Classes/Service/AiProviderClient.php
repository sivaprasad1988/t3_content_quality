<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Single HTTP client for Anthropic (Messages API), OpenAI (Chat Completions)
 * and Ollama (/api/chat). Returns the model's text; callers parse it.
 *
 * Raw HTTP through TYPO3's RequestFactory is used instead of the vendor SDKs
 * so the extension also works in classic (non-Composer) TER installations.
 */
class AiProviderClient
{
    public const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    public const ANTHROPIC_VERSION = '2023-06-01';
    public const ANTHROPIC_FALLBACK_BETA = 'server-side-fallback-2026-07-01';
    public const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    /** Models that accept `fallbacks: "default"` (server-side refusal fallback). */
    private const ANTHROPIC_DEFAULT_FALLBACK_MODELS = [
        'claude-sonnet-5-5',
        'claude-opus-5-5',
        'claude-opus-5',
        'claude-fable-5-1',
    ];

    private const TIMEOUT_TEXT = 120;
    private const TIMEOUT_OLLAMA = 180;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly OllamaEndpoint $ollamaEndpoint,
    ) {}

    public function call(string $prompt, AiSettings $settings): string
    {
        return match ($settings->provider) {
            AiSettingsFactory::PROVIDER_OPENAI => $this->callOpenAi([['role' => 'user', 'content' => $prompt]], $settings),
            AiSettingsFactory::PROVIDER_OLLAMA => $this->callOllama(['role' => 'user', 'content' => $prompt], $settings, false),
            default => $this->callAnthropic([['type' => 'text', 'text' => $prompt]], $settings),
        };
    }

    /** Prompt plus one image (base64, without data: prefix). */
    public function callVision(string $prompt, string $imageBase64, string $mimeType, AiSettings $settings): string
    {
        return match ($settings->provider) {
            AiSettingsFactory::PROVIDER_OPENAI => $this->callOpenAi([[
                'role' => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ]], $settings),
            AiSettingsFactory::PROVIDER_OLLAMA => $this->callOllama(
                ['role' => 'user', 'content' => $prompt, 'images' => [$imageBase64]],
                $settings,
                true
            ),
            default => $this->callAnthropic([
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $imageBase64]],
                ['type' => 'text', 'text' => $prompt],
            ], $settings),
        };
    }

    public function isOllamaReachable(): bool
    {
        try {
            $response = $this->requestFactory->request(
                $this->ollamaEndpoint->baseUrl(),
                'GET',
                ['timeout' => 3, 'connect_timeout' => 2]
            );
            return $response->getStatusCode() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Builds the Messages API request body and headers. Public for tests.
     *
     * @return array{body: array<string, mixed>, headers: array<string, string>}
     */
    public function buildAnthropicRequest(array $content, AiSettings $settings): array
    {
        $body = [
            'model' => $settings->model,
            'max_tokens' => $settings->maxTokens,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];
        $headers = [
            'x-api-key' => $settings->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ];

        // Effort is not accepted by Haiku 4.5 and the Claude 3 family.
        if ($settings->anthropicEffort !== '' && !preg_match('/^claude-(haiku|3)/', $settings->model)) {
            $body['output_config'] = ['effort' => $settings->anthropicEffort];
        }

        if ($settings->anthropicFallback && in_array($settings->model, self::ANTHROPIC_DEFAULT_FALLBACK_MODELS, true)) {
            $body['fallbacks'] = 'default';
            $headers['anthropic-beta'] = self::ANTHROPIC_FALLBACK_BETA;
        }

        return ['body' => $body, 'headers' => $headers];
    }

    /**
     * Extracts the answer from a Messages API response. Content is read by
     * block type because thinking blocks can precede the text block.
     * Public for tests.
     */
    public function parseAnthropicResponse(array $data): string
    {
        $stopReason = (string)($data['stop_reason'] ?? '');
        if ($stopReason === 'refusal') {
            $category = (string)($data['stop_details']['category'] ?? 'unknown');
            throw new \RuntimeException(sprintf('Anthropic declined the request (refusal category: %s).', $category));
        }

        $text = '';
        foreach ((array)($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string)($block['text'] ?? '');
            }
        }

        if ($text === '') {
            throw new \RuntimeException($stopReason === 'max_tokens'
                ? 'Anthropic response hit maxTokens before producing text. Increase maxTokens.'
                : 'Unexpected Anthropic API response structure.');
        }

        return trim($text);
    }

    private function callAnthropic(array $content, AiSettings $settings): string
    {
        $request = $this->buildAnthropicRequest($content, $settings);
        $data = $this->postJson(self::ANTHROPIC_URL, $request['body'], $request['headers'], self::TIMEOUT_TEXT, 'Anthropic');

        return $this->parseAnthropicResponse($data);
    }

    private function callOpenAi(array $messages, AiSettings $settings): string
    {
        $data = $this->postJson(
            self::OPENAI_URL,
            ['model' => $settings->model, 'max_tokens' => $settings->maxTokens, 'messages' => $messages],
            ['Authorization' => 'Bearer ' . $settings->apiKey, 'content-type' => 'application/json'],
            self::TIMEOUT_TEXT,
            'OpenAI'
        );

        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new \RuntimeException('Unexpected OpenAI API response structure.');
        }

        return trim($text);
    }

    private function callOllama(array $message, AiSettings $settings, bool $jsonFormat): string
    {
        $body = ['model' => $settings->model, 'stream' => false, 'messages' => [$message]];
        if ($jsonFormat) {
            $body['format'] = 'json';
        }

        $data = $this->postJson(
            $this->ollamaEndpoint->chatUrl(),
            $body,
            ['content-type' => 'application/json'],
            self::TIMEOUT_OLLAMA,
            'Ollama'
        );

        $text = $data['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new \RuntimeException('Unexpected Ollama response structure.');
        }

        return trim($text);
    }

    private function postJson(string $url, array $body, array $headers, int $timeout, string $label): array
    {
        $response = $this->requestFactory->request($url, 'POST', [
            'headers' => $headers,
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
            'timeout' => $timeout,
            'http_errors' => false,
        ]);

        $raw = (string)$response->getBody();
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('%s API returned %d: %s', $label, $response->getStatusCode(), mb_substr($raw, 0, 500)));
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('%s API returned invalid JSON.', $label));
        }

        return $data;
    }
}

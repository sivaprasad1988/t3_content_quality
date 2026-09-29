<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Service\AiSettingsFactory;

class AiSchemaDetector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiProviderClient $aiProviderClient,
    ) {}

    /**
     * Returns ['type' => 'Event', 'fields' => [...]] or null on failure/not configured.
     */
    public function detect(array $pageData, AiSettings $settings): ?array
    {
        if (!$settings->isUsable()) {
            return null;
        }

        if ($settings->provider === AiSettingsFactory::PROVIDER_OLLAMA && !$this->aiProviderClient->isOllamaReachable()) {
            $this->logger?->warning('AI schema detection skipped: Ollama not reachable');
            return null;
        }

        $prompt = $this->buildPrompt($pageData);

        try {
            $raw = $this->aiProviderClient->call($prompt, $settings);

            return $this->parseResponse($raw);
        } catch (\Throwable $e) {
            $this->logger?->warning('AI schema detection failed', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    private function buildPrompt(array $pageData): string
    {
        $title  = mb_substr((string)($pageData['page_title'] ?? ''), 0, 120);
        $desc   = mb_substr((string)($pageData['meta_description'] ?? ''), 0, 200);
        $author = mb_substr((string)($pageData['author'] ?? ''), 0, 60);
        $url    = mb_substr((string)($pageData['page_url'] ?? ''), 0, 120);
        $crdate = $pageData['crdate'] > 0
            ? (new \DateTimeImmutable('@' . (int)$pageData['crdate']))->format('Y-m-d')
            : '';
        $text   = mb_substr(trim((string)($pageData['text_content'] ?? '')), 0, 300);

        return <<<PROMPT
Classify this web page and extract schema.org fields. Return ONLY valid JSON, no markdown.

Title: {$title}
Description: {$desc}
Author: {$author}
URL: {$url}
Date: {$crdate}
Content: {$text}

Pick ONE type: Event, NewsArticle, TouristAttraction, LocalBusiness, Organization, FAQPage, JobPosting, Product, WebPage.
Only include fields you can fill from the data above. Use ISO 8601 for dates.

{"type":"WebPage","fields":{"name":"...","description":"..."}}
PROMPT;
    }

    public function parseResponse(string $raw): ?array
    {
        $raw = trim($raw);

        // Strip markdown code fences if present
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode(trim($raw), true);

        if (!is_array($data) || empty($data['type'])) {
            return null;
        }

        $type   = (string)$data['type'];
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        return ['type' => $type, 'fields' => $fields];
    }
}

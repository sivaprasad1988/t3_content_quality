<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Woit\T3ContentQuality\Service\InternalLinkSuggester;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * AI-only: suggests related internal pages to link from the current page's content.
 * No rule-based fallback — silently produces no suggestions when AI is unavailable.
 */
class InternalLinkAnalyzer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiProviderClient $aiProviderClient,
        private readonly InternalLinkSuggester $internalLinkSuggester,
    ) {}

    public function analyze(
        array $pageData,
        AnalysisResult $result,
        AiSettings $settings,
    ): void {
        if (!$settings->isUsable()) {
            return;
        }

        $candidates = $this->internalLinkSuggester->getCandidatePages((int)($pageData['page_uid'] ?? 0));
        if (empty($candidates)) {
            return;
        }

        $prompt = $this->buildPrompt($pageData, $candidates);

        try {
            $text = $this->aiProviderClient->call($prompt, $settings);
            $suggestions = json_decode(trim($text), true);
            $result->metadata['link_suggestions'] = is_array($suggestions)
                ? array_values(array_filter($suggestions, static fn($s) => is_array($s) && !empty($s['uid'])))
                : [];
        } catch (\Throwable $e) {
            $this->logger?->error('Internal link suggestion failed', ['exception' => $e->getMessage()]);
        }
    }

    private function buildPrompt(array $pageData, array $candidates): string
    {
        $textSnippet = mb_substr(trim($pageData['text_content'] ?? ''), 0, 1500);
        $title = $pageData['page_title'] ?? '';

        $candidateList = implode("\n", array_map(
            static fn(array $c) => sprintf('- uid=%d, title="%s", slug="%s"', $c['uid'], $c['title'], $c['slug']),
            $candidates
        ));

        return <<<PROMPT
You are an internal linking assistant for a TYPO3 website. Given the current page's content and a list of other pages on the site, suggest 3-5 pages that would be relevant to link to from this page's body text.

Current page title: {$title}
Current page text (excerpt):
{$textSnippet}

Candidate pages (uid, title, slug):
{$candidateList}

Return a JSON array of objects, each with "uid" (int, must match a candidate uid), "title" (string), and "reason" (short string explaining why this link is relevant). Only suggest pages that are genuinely topically related. Example:
[{"uid": 12, "title": "Standort Unna", "reason": "Mentioned location is covered in depth on this page."}]

Return only the JSON array, no other text.
PROMPT;
    }
}

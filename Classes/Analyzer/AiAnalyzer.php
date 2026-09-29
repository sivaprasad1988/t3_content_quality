<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class AiAnalyzer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiProviderClient $aiProviderClient,
    ) {}

    public function analyze(
        array $pageData,
        AnalysisResult $result,
        AiSettings $settings,
    ): void {
        if (!$settings->isUsable()) {
            return;
        }

        if ($settings->provider === AiSettingsFactory::PROVIDER_OLLAMA && !$this->aiProviderClient->isOllamaReachable()) {
            $this->logger?->warning('AI analysis skipped: Ollama not reachable');
            return;
        }

        $prompt = $this->buildPrompt($pageData, $result);

        try {
            $text = $this->aiProviderClient->call($prompt, $settings);
            $suggestions = json_decode(trim($text), true);
            $suggestions = is_array($suggestions) ? array_values(array_filter($suggestions, 'is_string')) : [];

            $result->setAiSuggestions($suggestions);
            $result->modelUsed = $settings->model;
            $result->providerUsed = $settings->provider;
        } catch (\Throwable $e) {
            $this->logger?->error('AI analysis failed', ['exception' => $e->getMessage()]);
        }
    }

    private function buildPrompt(array $pageData, AnalysisResult $result): string
    {
        $issueList = implode(
            "\n",
            array_map(static fn($i) => '- ' . $i->message, $result->getIssues())
        );

        $textSnippet = mb_substr(trim($pageData['text_content'] ?? ''), 0, 1500);
        $title = $pageData['page_title'] ?? '';
        $meta = $pageData['meta_description'] ?? '';

        return <<<PROMPT
You are a content quality assistant for a TYPO3 website. Analyse this page and provide exactly 5 concrete, actionable improvement suggestions.

Page title: {$title}
Meta description: {$meta}

Issues already detected:
{$issueList}

Text content (excerpt):
{$textSnippet}

Return exactly 5 short improvement suggestions as a JSON array of strings. Example:
["Add ALT text to the event images.", "Replace 'Mehr erfahren' links with descriptive text.", "Add a summary paragraph at the beginning.", "Shorten sentences in the main body.", "Add one internal link to related content."]

Return only the JSON array, no other text.
PROMPT;
    }
}

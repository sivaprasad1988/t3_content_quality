<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Domain\Model;

class AnalysisResult
{
    /** @var AnalysisIssue[] */
    private array $issues = [];

    private array $aiSuggestions = [];

    public int $overallScore = 0;
    public int $accessibilityScore = 0;
    public int $seoScore = 0;
    public int $readabilityScore = 0;
    public int $schemaScore = 0;

    public string $modelUsed = '';
    public string $providerUsed = '';
    public array $metadata = [];

    public function addIssue(AnalysisIssue $issue): void
    {
        $this->issues[] = $issue;
    }

    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getIssuesByCategory(string $category): array
    {
        return array_values(
            array_filter($this->issues, static fn(AnalysisIssue $i) => $i->category === $category)
        );
    }

    public function setAiSuggestions(array $suggestions): void
    {
        $this->aiSuggestions = $suggestions;
    }

    public function getAiSuggestions(): array
    {
        return $this->aiSuggestions;
    }

    public function issuesToArray(): array
    {
        return array_map(static fn(AnalysisIssue $i) => $i->toArray(), $this->issues);
    }
}

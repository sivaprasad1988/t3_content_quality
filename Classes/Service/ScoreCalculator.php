<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;

class ScoreCalculator
{
    private const PENALTY = [
        AnalysisIssue::SEVERITY_ERROR => 20,
        AnalysisIssue::SEVERITY_WARNING => 10,
        AnalysisIssue::SEVERITY_INFO => 3,
    ];

    public function calculate(AnalysisResult $result): void
    {
        $result->accessibilityScore = $this->scoreForCategory(
            $result->getIssuesByCategory(AnalysisIssue::CATEGORY_ACCESSIBILITY)
        );
        $result->seoScore = $this->scoreForCategory(
            $result->getIssuesByCategory(AnalysisIssue::CATEGORY_SEO)
        );
        $result->readabilityScore = $this->scoreForCategory(
            $result->getIssuesByCategory(AnalysisIssue::CATEGORY_READABILITY)
        );

        $result->overallScore = (int)round(
            ($result->accessibilityScore + $result->seoScore + $result->readabilityScore) / 3
        );
    }

    private function scoreForCategory(array $issues): int
    {
        $penalty = 0;
        foreach ($issues as $issue) {
            $penalty += self::PENALTY[$issue->severity] ?? 0;
        }

        return max(0, 100 - $penalty);
    }
}

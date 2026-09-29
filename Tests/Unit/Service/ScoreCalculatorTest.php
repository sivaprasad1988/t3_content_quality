<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use Woit\T3ContentQuality\Service\ScoreCalculator;

final class ScoreCalculatorTest extends TestCase
{
    #[Test]
    public function pageWithoutIssuesScoresHundredEverywhere(): void
    {
        $result = new AnalysisResult();
        (new ScoreCalculator())->calculate($result);

        self::assertSame(100, $result->accessibilityScore);
        self::assertSame(100, $result->seoScore);
        self::assertSame(100, $result->readabilityScore);
        self::assertSame(100, $result->overallScore);
    }

    #[Test]
    public function penaltiesAreDeductedPerSeverityAndOverallIsTheAverage(): void
    {
        $result = new AnalysisResult();
        $result->addIssue(new AnalysisIssue(AnalysisIssue::CATEGORY_ACCESSIBILITY, AnalysisIssue::SEVERITY_ERROR, 'e'));
        $result->addIssue(new AnalysisIssue(AnalysisIssue::CATEGORY_SEO, AnalysisIssue::SEVERITY_WARNING, 'w'));
        $result->addIssue(new AnalysisIssue(AnalysisIssue::CATEGORY_READABILITY, AnalysisIssue::SEVERITY_INFO, 'i'));

        (new ScoreCalculator())->calculate($result);

        self::assertSame(80, $result->accessibilityScore);
        self::assertSame(90, $result->seoScore);
        self::assertSame(97, $result->readabilityScore);
        self::assertSame(89, $result->overallScore);
    }

    #[Test]
    public function schemaIssuesDoNotAffectOverallScore(): void
    {
        $result = new AnalysisResult();
        $result->addIssue(new AnalysisIssue(AnalysisIssue::CATEGORY_SCHEMA, AnalysisIssue::SEVERITY_ERROR, 's'));

        (new ScoreCalculator())->calculate($result);

        self::assertSame(100, $result->overallScore);
    }

    #[Test]
    public function scoreNeverDropsBelowZero(): void
    {
        $result = new AnalysisResult();
        for ($i = 0; $i < 10; $i++) {
            $result->addIssue(new AnalysisIssue(AnalysisIssue::CATEGORY_SEO, AnalysisIssue::SEVERITY_ERROR, 'e'));
        }

        (new ScoreCalculator())->calculate($result);

        self::assertSame(0, $result->seoScore);
    }
}

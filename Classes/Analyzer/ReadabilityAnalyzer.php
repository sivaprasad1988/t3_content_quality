<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ReadabilityAnalyzer
{
    private const LLL = 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:';

    private const LONG_SENTENCE_WORD_LIMIT = 25;
    private const MIN_WORD_COUNT = 50;

    private static function translate(string $key, string $fallback): string
    {
        return LocalizationUtility::translate(self::LLL . $key) ?? $fallback;
    }

    public function analyze(array $pageData, AnalysisResult $result): void
    {
        $text = trim($pageData['text_content'] ?? '');
        if ($text === '') {
            return;
        }

        $this->checkLongSentences($text, $result);
        $this->checkTextLength($text, $result);
        $this->checkFlesch($text, $result);
        $this->calculateReadingTime($text, $result);
    }

    private function calculateReadingTime(string $text, AnalysisResult $result): void
    {
        $wordCount = str_word_count(strip_tags($text));
        // 200 wpm ~ average German reading speed
        $result->metadata['reading_time_minutes'] = max(1, (int)ceil($wordCount / 200));
    }

    private function checkLongSentences(string $text, AnalysisResult $result): void
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$sentences) {
            return;
        }

        $longSentences = 0;
        $firstExample = '';
        foreach ($sentences as $sentence) {
            if (str_word_count($sentence) > self::LONG_SENTENCE_WORD_LIMIT) {
                $longSentences++;
                if ($firstExample === '') {
                    $trimmed = trim($sentence);
                    $firstExample = mb_strlen($trimmed) > 100
                        ? mb_substr($trimmed, 0, 100) . '…'
                        : $trimmed;
                }
            }
        }

        if ($longSentences > 0) {
            if ($longSentences > 1) {
                $message = sprintf(
                    self::translate('readability.longSentences.message.other', '%d sentences exceed %d words. (e.g. "%s")'),
                    $longSentences,
                    self::LONG_SENTENCE_WORD_LIMIT,
                    $firstExample
                );
            } else {
                $message = sprintf(
                    self::translate('readability.longSentences.message.one', '1 sentence exceeds %d words. (e.g. "%s")'),
                    self::LONG_SENTENCE_WORD_LIMIT,
                    $firstExample
                );
            }
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_READABILITY,
                $longSentences > 5 ? AnalysisIssue::SEVERITY_WARNING : AnalysisIssue::SEVERITY_INFO,
                $message,
                self::translate('readability.longSentences.suggestion', 'Break long sentences into shorter ones (aim for under 25 words per sentence).')
            ));
        }
    }

    private function checkTextLength(string $text, AnalysisResult $result): void
    {
        $wordCount = str_word_count($text);
        if ($wordCount < self::MIN_WORD_COUNT) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_READABILITY,
                AnalysisIssue::SEVERITY_INFO,
                sprintf(self::translate('readability.shortText.message', 'Page has very little text (%d words).'), $wordCount),
                self::translate('readability.shortText.suggestion', 'Consider adding more content to provide value to readers and search engines.')
            ));
        }
    }

    private function checkFlesch(string $text, AnalysisResult $result): void
    {
        $score = $this->fleschAmstad($text);
        $result->metadata['flesch_score'] = $score;

        if ($score < 30) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_READABILITY,
                AnalysisIssue::SEVERITY_WARNING,
                sprintf(self::translate('readability.fleschVeryDifficult.message', 'Flesch Reading Ease: %.0f/100 — text is very difficult to read.'), $score),
                self::translate('readability.fleschVeryDifficult.suggestion', 'Simplify language: shorter sentences, common everyday words.')
            ));
        } elseif ($score < 60) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_READABILITY,
                AnalysisIssue::SEVERITY_INFO,
                sprintf(self::translate('readability.fleschModerate.message', 'Flesch Reading Ease: %.0f/100 — moderately difficult.'), $score),
                self::translate('readability.fleschModerate.suggestion', 'Consider simplifying for B1-level readers.')
            ));
        }
    }

    /**
     * German Amstad variant of Flesch Reading Ease.
     * Score 0–100: higher = easier to read.
     */
    private function fleschAmstad(string $text): float
    {
        $text = preg_replace('/\s+/', ' ', trim(strip_tags($text)));

        $sentenceParts = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = max(1, count(array_filter($sentenceParts, static fn($s) => trim($s) !== '')));

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = max(1, count($words));

        $syllableCount = 0;
        foreach ($words as $word) {
            $syllableCount += $this->countSyllables($word);
        }
        $syllableCount = max(1, $syllableCount);

        // Amstad: 180 - (words/sentences) - 58.5 × (syllables/words)
        $score = 180.0
            - ($wordCount / $sentenceCount)
            - 58.5 * ($syllableCount / $wordCount);

        return round(min(100.0, max(0.0, $score)), 1);
    }

    private function countSyllables(string $word): int
    {
        $word = mb_strtolower(preg_replace('/[^a-zA-ZäöüÄÖÜß]/u', '', $word) ?? '');
        if ($word === '') {
            return 1;
        }
        // Count consecutive vowel groups as one syllable each
        $count = preg_match_all('/[aeiouäöüy]+/ui', $word);
        return max(1, (int)$count);
    }
}

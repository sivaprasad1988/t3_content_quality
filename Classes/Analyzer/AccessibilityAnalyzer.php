<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AccessibilityAnalyzer
{
    private const LLL = 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:';

    private const GENERIC_ALT_TEXTS = ['image', 'photo', 'bild', 'foto', 'picture', 'img'];
    private const GENERIC_LINK_TEXTS = [
        'click here', 'read more', 'mehr erfahren', 'hier klicken',
        'more', 'weiter', 'link', 'here', 'klicken',
    ];

    private static function translate(string $key, string $fallback): string
    {
        return LocalizationUtility::translate(self::LLL . $key) ?? $fallback;
    }

    public function analyze(array $pageData, AnalysisResult $result): void
    {
        $this->checkImages($pageData['images'] ?? [], $result);
        $this->checkHeadings($pageData['headings'] ?? [], $result);
        $this->checkLinks($pageData['links'] ?? [], $result);
    }

    private function checkImages(array $images, AnalysisResult $result): void
    {
        $missingAlt = 0;
        $genericAlt = 0;

        foreach ($images as $image) {
            $alt = trim((string)($image['alternative'] ?? ''));
            if ($alt === '') {
                $missingAlt++;
            } elseif (
                in_array(strtolower($alt), self::GENERIC_ALT_TEXTS, true)
                || (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $alt)
            ) {
                $genericAlt++;
            }
        }

        if ($missingAlt > 0) {
            $messageTemplate = self::translate(
                'accessibility.missingAlt.message.' . ($missingAlt > 1 ? 'other' : 'one'),
                $missingAlt > 1 ? '%d images are missing ALT text.' : '1 image is missing ALT text.'
            );
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_ACCESSIBILITY,
                AnalysisIssue::SEVERITY_ERROR,
                $missingAlt > 1 ? sprintf($messageTemplate, $missingAlt) : $messageTemplate,
                self::translate('accessibility.missingAlt.suggestion', 'Add descriptive ALT text to every meaningful image.')
            ));
        }

        if ($genericAlt > 0) {
            $messageTemplate = self::translate(
                'accessibility.genericAlt.message.' . ($genericAlt > 1 ? 'other' : 'one'),
                $genericAlt > 1 ? '%d images have generic or filename-based ALT text.' : '1 image has generic or filename-based ALT text.'
            );
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_ACCESSIBILITY,
                AnalysisIssue::SEVERITY_WARNING,
                $genericAlt > 1 ? sprintf($messageTemplate, $genericAlt) : $messageTemplate,
                self::translate('accessibility.genericAlt.suggestion', 'Replace generic ALT text with a meaningful image description.')
            ));
        }
    }

    private function checkHeadings(array $headings, AnalysisResult $result): void
    {
        if (empty($headings)) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_ACCESSIBILITY,
                AnalysisIssue::SEVERITY_WARNING,
                self::translate('accessibility.noHeadings.message', 'Page has no headings.'),
                self::translate('accessibility.noHeadings.suggestion', 'Add a clear heading structure (H1, H2, H3) to organise the content.')
            ));
            return;
        }

        $h1Count = 0;
        $prevLevel = 0;
        foreach ($headings as $heading) {
            $level = (int)($heading['level'] ?? 1);
            if ($level === 1) {
                $h1Count++;
            }
            if ($prevLevel > 0 && $level > $prevLevel + 1) {
                $headingText = trim((string)($heading['text'] ?? ''));
                $result->addIssue(new AnalysisIssue(
                    AnalysisIssue::CATEGORY_ACCESSIBILITY,
                    AnalysisIssue::SEVERITY_ERROR,
                    sprintf(
                        self::translate('accessibility.headingJump.message', 'Heading structure jumps from H%d to H%d. Missing H%d. (At heading: "%s")'),
                        $prevLevel,
                        $level,
                        $prevLevel + 1,
                        $headingText !== '' ? $headingText : '–'
                    ),
                    self::translate('accessibility.headingJump.suggestion', 'Use sequential heading levels. Do not skip levels (e.g. H2 → H4).')
                ));
            }
            $prevLevel = $level;
        }

        if ($h1Count === 0) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_ACCESSIBILITY,
                AnalysisIssue::SEVERITY_ERROR,
                self::translate('accessibility.noH1.message', 'Page has no H1 heading.'),
                self::translate('accessibility.noH1.suggestion', 'Every page should have exactly one H1 heading as the main title.')
            ));
        } elseif ($h1Count > 1) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_ACCESSIBILITY,
                AnalysisIssue::SEVERITY_WARNING,
                sprintf(self::translate('accessibility.multipleH1.message', 'Page has %d H1 headings. Only one H1 is recommended.'), $h1Count),
                self::translate('accessibility.multipleH1.suggestion', 'Use a single H1 as the main page title. Use H2–H6 for sub-sections.')
            ));
        }
    }

    private function checkLinks(array $links, AnalysisResult $result): void
    {
        $genericCount = 0;
        foreach ($links as $link) {
            $normalized = strtolower(trim(strip_tags((string)($link['text'] ?? ''))));
            if (in_array($normalized, self::GENERIC_LINK_TEXTS, true)) {
                $genericCount++;
            }
        }

        if ($genericCount > 0) {
            $messageTemplate = self::translate(
                'accessibility.genericLinkText.message.' . ($genericCount > 1 ? 'other' : 'one'),
                $genericCount > 1
                    ? '%d links use generic text (e.g. "Click here", "Mehr erfahren").'
                    : '1 link uses generic text (e.g. "Click here", "Mehr erfahren").'
            );
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_ACCESSIBILITY,
                AnalysisIssue::SEVERITY_WARNING,
                $genericCount > 1 ? sprintf($messageTemplate, $genericCount) : $messageTemplate,
                self::translate('accessibility.genericLinkText.suggestion', 'Use descriptive link text that explains the destination.')
            ));
        }
    }
}

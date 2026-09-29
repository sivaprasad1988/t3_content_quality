<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class SeoAnalyzer
{
    private const LLL = 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:';

    private static function translate(string $key, string $fallback): string
    {
        return LocalizationUtility::translate(self::LLL . $key) ?? $fallback;
    }

    public function analyze(array $pageData, AnalysisResult $result): void
    {
        $seoTitle = trim((string)($pageData['seo_title'] ?? ''));
        $effectiveTitle = $seoTitle !== '' ? $seoTitle : ($pageData['page_title'] ?? '');

        $this->checkMetaDescription($pageData['meta_description'] ?? '', $result);
        $this->checkPageTitle($effectiveTitle, $result);
        $this->checkInternalLinks($pageData['links'] ?? [], $result);
    }

    private function checkMetaDescription(string $metaDesc, AnalysisResult $result): void
    {
        if ($metaDesc === '') {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_ERROR,
                self::translate('seo.missingMetaDescription.message', 'Meta description is missing.'),
                self::translate('seo.missingMetaDescription.suggestion', 'Add a meta description between 120–160 characters summarising the page content.')
            ));
            return;
        }

        $length = mb_strlen($metaDesc);
        if ($length < 70) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_WARNING,
                sprintf(self::translate('seo.metaDescriptionTooShort.message', 'Meta description is too short (%d characters). Recommended: 120–160.'), $length),
                self::translate('seo.metaDescriptionTooShort.suggestion', 'Expand the meta description to 120–160 characters.')
            ));
        } elseif ($length > 160) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_WARNING,
                sprintf(self::translate('seo.metaDescriptionTooLong.message', 'Meta description is too long (%d characters). Recommended: 120–160.'), $length),
                self::translate('seo.metaDescriptionTooLong.suggestion', 'Shorten the meta description to under 160 characters.')
            ));
        }
    }

    private function checkPageTitle(string $title, AnalysisResult $result): void
    {
        if ($title === '') {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_ERROR,
                self::translate('seo.missingTitle.message', 'Page title is missing.'),
                self::translate('seo.missingTitle.suggestion', 'Add a descriptive page title between 30–60 characters.')
            ));
            return;
        }

        $length = mb_strlen($title);
        if ($length < 10) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_WARNING,
                sprintf(self::translate('seo.titleTooShort.message', 'Page title is very short (%d characters). Recommended: 30–60. (Title: "%s")'), $length, $title),
                self::translate('seo.titleTooShort.suggestion', 'Use a descriptive title between 30–60 characters.')
            ));
        } elseif ($length > 70) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_WARNING,
                sprintf(self::translate('seo.titleTooLong.message', 'Page title is too long (%d characters). Recommended: 30–60. (Title: "%s")'), $length, $title),
                self::translate('seo.titleTooLong.suggestion', 'Shorten the page title to under 60 characters.')
            ));
        }
    }

    private function checkInternalLinks(array $links, AnalysisResult $result): void
    {
        if (count($links) === 0) {
            $result->addIssue(new AnalysisIssue(
                AnalysisIssue::CATEGORY_SEO,
                AnalysisIssue::SEVERITY_INFO,
                self::translate('seo.noLinks.message', 'No links found on this page.'),
                self::translate('seo.noLinks.suggestion', 'Consider adding internal links to related pages to improve navigation and SEO.')
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DuplicateContentAnalyzer
{
    private const LLL = 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:';

    private const SIMILARITY_THRESHOLD = 85.0;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    private static function translate(string $key, string $fallback): string
    {
        return LocalizationUtility::translate(self::LLL . $key) ?? $fallback;
    }

    public function analyze(array $pageData, AnalysisResult $result): void
    {
        $pageUid = (int)($pageData['page_uid'] ?? 0);
        $title = trim((string)($pageData['page_title'] ?? ''));
        $metaDescription = trim((string)($pageData['meta_description'] ?? ''));

        if ($pageUid === 0 || ($title === '' && $metaDescription === '')) {
            return;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $otherPages = $qb->select('uid', 'title', 'description')
            ->from('pages')
            ->where(
                $qb->expr()->eq('doktype', $qb->createNamedParameter(1, Connection::PARAM_INT)),
                $qb->expr()->eq('hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->neq('uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($otherPages as $other) {
            $otherTitle = trim((string)($other['title'] ?? ''));
            $otherDescription = trim((string)($other['description'] ?? ''));

            if ($title !== '' && $otherTitle !== '') {
                $similarity = $this->similarity($title, $otherTitle);
                if ($similarity >= self::SIMILARITY_THRESHOLD) {
                    $result->addIssue(new AnalysisIssue(
                        AnalysisIssue::CATEGORY_SEO,
                        AnalysisIssue::SEVERITY_WARNING,
                        sprintf(
                            self::translate('duplicateContent.titleSimilar.message', 'Page title is %.0f%% similar to page %d ("%s").'),
                            $similarity,
                            (int)$other['uid'],
                            $otherTitle
                        ),
                        self::translate('duplicateContent.titleSimilar.suggestion', 'Use a more distinct, page-specific title to avoid confusing search engines and editors.')
                    ));
                }
            }

            if ($metaDescription !== '' && $otherDescription !== '') {
                $similarity = $this->similarity($metaDescription, $otherDescription);
                if ($similarity >= self::SIMILARITY_THRESHOLD) {
                    $result->addIssue(new AnalysisIssue(
                        AnalysisIssue::CATEGORY_SEO,
                        AnalysisIssue::SEVERITY_WARNING,
                        sprintf(
                            self::translate('duplicateContent.descriptionSimilar.message', 'Meta description is %.0f%% similar to page %d ("%s").'),
                            $similarity,
                            (int)$other['uid'],
                            $otherTitle
                        ),
                        self::translate('duplicateContent.descriptionSimilar.suggestion', "Write a unique meta description that reflects this specific page's content.")
                    ));
                }
            }
        }
    }

    private function similarity(string $a, string $b): float
    {
        similar_text(mb_strtolower($a), mb_strtolower($b), $percent);
        return $percent;
    }
}

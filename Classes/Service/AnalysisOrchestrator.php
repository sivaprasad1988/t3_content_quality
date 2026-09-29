<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use Woit\T3ContentQuality\Analyzer\AccessibilityAnalyzer;
use Woit\T3ContentQuality\Analyzer\AiAnalyzer;
use Woit\T3ContentQuality\Analyzer\DuplicateContentAnalyzer;
use Woit\T3ContentQuality\Analyzer\InternalLinkAnalyzer;
use Woit\T3ContentQuality\Analyzer\ReadabilityAnalyzer;
use Woit\T3ContentQuality\Analyzer\SchemaAdvisor;
use Woit\T3ContentQuality\Analyzer\SeoAnalyzer;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;

class AnalysisOrchestrator
{
    public function __construct(
        private readonly PageContentExtractor $contentExtractor,
        private readonly AccessibilityAnalyzer $accessibilityAnalyzer,
        private readonly SeoAnalyzer $seoAnalyzer,
        private readonly ReadabilityAnalyzer $readabilityAnalyzer,
        private readonly AiAnalyzer $aiAnalyzer,
        private readonly SchemaAdvisor $schemaAdvisor,
        private readonly InternalLinkAnalyzer $internalLinkAnalyzer,
        private readonly DuplicateContentAnalyzer $duplicateContentAnalyzer,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly ConnectionPool $connectionPool,
        private readonly AiSettingsFactory $aiSettingsFactory,
        private readonly SiteFinder $siteFinder,
        private readonly RenderedHeadingExtractor $renderedHeadingExtractor,
    ) {}

    public function analyze(int $pageUid, int $languageUid = 0): AnalysisResult
    {
        $pageData = $this->contentExtractor->extractFromPage($pageUid, $languageUid);
        $result = new AnalysisResult();

        // Resolve site info once — used by schema mapper and SERP preview
        $siteHost = '';
        $siteBaseUrl = '';
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $base = $site->getBase();
            $siteHost = $base->getHost();
            $siteBaseUrl = rtrim((string)$base, '/');
            $pageData['page_url'] = $siteBaseUrl . ($pageData['slug'] ?? '');
        } catch (\Throwable) {
            $pageData['page_url'] = '';
        }

        // Prefer the real rendered heading structure over the header_layout-based
        // guess: custom content element templates on this site frequently render
        // their header field as a plain <div>, not a semantic heading tag, so the
        // DB field alone overcounts H1s. Falls back to the DB-derived guess when
        // the page can't be fetched (offline, protected, etc.).
        $renderedHeadings = $this->renderedHeadingExtractor->extract($pageData['page_url']);
        if ($renderedHeadings !== null) {
            $pageData['headings'] = $renderedHeadings;
        }

        $this->accessibilityAnalyzer->analyze($pageData, $result);
        $this->seoAnalyzer->analyze($pageData, $result);
        $this->readabilityAnalyzer->analyze($pageData, $result);
        $this->duplicateContentAnalyzer->analyze($pageData, $result);
        $aiSettings = $this->aiSettingsFactory->create();
        $aiEnabled = $aiSettings->analysisEnabled && $aiSettings->isUsable();

        // Schema detection uses rules only during main analysis — fast path.
        // AI-powered schema detection is triggered separately via analyzeSchemaWithAi().
        $this->schemaAdvisor->analyze($pageData, $result);

        if ($aiEnabled) {
            $this->aiAnalyzer->analyze($pageData, $result, $aiSettings);
            $this->internalLinkAnalyzer->analyze($pageData, $result, $aiSettings);
        }

        $this->scoreCalculator->calculate($result);

        $result->metadata['page_title'] = $pageData['page_title'] ?? '';
        $result->metadata['seo_title'] = $pageData['seo_title'] ?? '';
        $result->metadata['meta_description'] = $pageData['meta_description'] ?? '';
        $result->metadata['site_host'] = $siteHost;
        $result->metadata['site_base_url'] = $siteBaseUrl;
        $result->metadata['page_uid'] = $pageUid;
        $result->metadata['headings'] = $pageData['headings'] ?? [];

        $this->storeResult($pageUid, $languageUid, $result);

        return $result;
    }

    public function getStoredResult(int $pageUid, int $languageUid = 0): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_result');
        $row = $qb
            ->select('*')
            ->from('tx_t3contentquality_result')
            ->where(
                $qb->expr()->eq('page_uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->orderBy('analyzed_at', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return null;
        }

        $row['issues'] = json_decode((string)($row['issues_json'] ?? '[]'), true) ?: [];
        $row['suggestions'] = json_decode((string)($row['suggestions_json'] ?? '[]'), true) ?: [];
        $row['metadata'] = json_decode((string)($row['metadata_json'] ?? '[]'), true) ?: [];
        $row['schema_score'] = (int)($row['schema_score'] ?? 0);
        $row['analyzed_at_display'] = $row['analyzed_at'] > 0
            ? (new \DateTimeImmutable('@' . $row['analyzed_at']))->format('d.m.Y H:i')
            : '';

        return $row;
    }

    /**
     * Returns detected schema type string on success, or an error/status string prefixed with 'error:'.
     */
    public function analyzeSchemaWithAi(int $pageUid, int $languageUid = 0): string
    {
        $aiSettings = $this->aiSettingsFactory->create();

        if (!$aiSettings->analysisEnabled) {
            return 'error:AI analysis is disabled. Enable it in Extension Configuration.';
        }
        if (!$aiSettings->isUsable()) {
            return 'error:No API key configured for provider "' . $aiSettings->provider . '".';
        }

        $pageData = $this->contentExtractor->extractFromPage($pageUid, $languageUid);

        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $pageData['page_url'] = rtrim((string)$site->getBase(), '/') . ($pageData['slug'] ?? '');
        } catch (\Throwable) {
            $pageData['page_url'] = '';
        }

        $tempResult = new \Woit\T3ContentQuality\Domain\Model\AnalysisResult();
        $this->schemaAdvisor->analyze($pageData, $tempResult, $aiSettings);

        // Patch only the schema portion of the stored result
        $connection = $this->connectionPool->getConnectionForTable('tx_t3contentquality_result');
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_result');
        $row = $qb->select('uid', 'metadata_json', 'issues_json')
            ->from('tx_t3contentquality_result')
            ->where(
                $qb->expr()->eq('page_uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->orderBy('analyzed_at', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return 'error:No analysis result found for page. Run a full analysis first.';
        }

        $schemaData     = $tempResult->metadata['schema'] ?? [];
        $detectedType   = (string)($schemaData['detected_type'] ?? '');
        $detectedBy     = (string)($schemaData['detected_by'] ?? 'rules');

        $metadata = json_decode((string)($row['metadata_json'] ?? '{}'), true) ?: [];
        $metadata['schema'] = $schemaData;

        $existingIssues = json_decode((string)($row['issues_json'] ?? '[]'), true) ?: [];
        $existingIssues = array_values(array_filter($existingIssues, static fn($i) => ($i['category'] ?? '') !== 'schema'));
        $schemaIssues   = array_filter($tempResult->issuesToArray(), static fn($i) => ($i['category'] ?? '') === 'schema');
        $mergedIssues   = array_values(array_merge($existingIssues, $schemaIssues));

        $connection->update(
            'tx_t3contentquality_result',
            [
                'metadata_json' => (string)(json_encode($metadata) ?: '[]'),
                'issues_json'   => (string)(json_encode($mergedIssues) ?: '[]'),
                'schema_score'  => $tempResult->schemaScore,
            ],
            ['uid' => (int)$row['uid']]
        );

        if ($detectedType === '') {
            return 'error:Could not detect a schema type. Try setting one manually in page properties.';
        }

        return sprintf(
            'ok:Schema detected as %s via %s (score: %d/100).',
            $detectedType,
            $detectedBy === 'ai' ? 'AI' : 'keyword rules',
            $tempResult->schemaScore
        );
    }

    /**
     * Runs the broken-link check for a page's already-stored links and patches
     * the 'broken_links' key into the stored result's metadata. Manual/on-demand
     * only — not run as part of analyze() since it makes live HTTP requests.
     */
    public function checkLinks(int $pageUid, int $languageUid, LinkChecker $linkChecker): array
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_t3contentquality_result');
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_result');
        $row = $qb->select('uid', 'metadata_json')
            ->from('tx_t3contentquality_result')
            ->where(
                $qb->expr()->eq('page_uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->orderBy('analyzed_at', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return [];
        }

        $metadata = json_decode((string)($row['metadata_json'] ?? '{}'), true) ?: [];
        $pageData = $this->contentExtractor->extractFromPage($pageUid, $languageUid);

        $siteBaseUrl = '';
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $siteBaseUrl = rtrim((string)$site->getBase(), '/');
        } catch (\Throwable) {
        }

        $brokenLinks = $linkChecker->checkLinks($pageData['links'] ?? [], $siteBaseUrl);
        $metadata['broken_links'] = $brokenLinks;
        $metadata['broken_links_checked_at'] = time();

        $connection->update(
            'tx_t3contentquality_result',
            ['metadata_json' => (string)(json_encode($metadata) ?: '[]')],
            ['uid' => (int)$row['uid']]
        );

        return $brokenLinks;
    }

    private function storeResult(int $pageUid, int $languageUid, AnalysisResult $result): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_t3contentquality_result');

        $connection->delete(
            'tx_t3contentquality_result',
            ['page_uid' => $pageUid, 'language_uid' => $languageUid],
            [Connection::PARAM_INT, Connection::PARAM_INT]
        );

        $connection->insert('tx_t3contentquality_result', [
            'pid' => $pageUid,
            'page_uid' => $pageUid,
            'language_uid' => $languageUid,
            'overall_score' => $result->overallScore,
            'accessibility_score' => $result->accessibilityScore,
            'seo_score' => $result->seoScore,
            'readability_score' => $result->readabilityScore,
            'issues_json' => json_encode($result->issuesToArray()),
            'suggestions_json' => json_encode($result->getAiSuggestions()),
            'metadata_json' => (string)(json_encode($result->metadata) ?: '[]'),
            'analyzed_at' => time(),
            'model_used' => $result->modelUsed,
            'provider_used' => $result->providerUsed,
            'schema_score' => $result->schemaScore,
        ]);

        $connection->insert('tx_t3contentquality_history', [
            'pid' => $pageUid,
            'page_uid' => $pageUid,
            'language_uid' => $languageUid,
            'overall_score' => $result->overallScore,
            'accessibility_score' => $result->accessibilityScore,
            'seo_score' => $result->seoScore,
            'readability_score' => $result->readabilityScore,
            'schema_score' => $result->schemaScore,
            'analyzed_at' => time(),
        ]);
    }

    /**
     * @return array<int, array{overall_score:int,accessibility_score:int,seo_score:int,readability_score:int,schema_score:int,analyzed_at:int}>
     */
    public function getHistory(int $pageUid, int $languageUid = 0, int $limit = 20): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_history');
        $rows = $qb
            ->select('*')
            ->from('tx_t3contentquality_history')
            ->where(
                $qb->expr()->eq('page_uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->orderBy('analyzed_at', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_reverse($rows);
    }

    /**
     * All analysed pages (default language only), worst score first.
     *
     * @return array<int, array{page_uid:int,page_title:string,overall_score:int,accessibility_score:int,seo_score:int,readability_score:int,schema_score:int,issue_count:int,analyzed_at:int,analyzed_at_display:string}>
     */
    public function getAllResults(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_result');
        $rows = $qb
            ->select('page_uid', 'overall_score', 'accessibility_score', 'seo_score', 'readability_score', 'schema_score', 'issues_json', 'metadata_json', 'analyzed_at')
            ->from('tx_t3contentquality_result')
            ->where($qb->expr()->eq('language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('overall_score', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static function (array $row): array {
            $metadata = json_decode((string)($row['metadata_json'] ?? '{}'), true) ?: [];
            $issues = json_decode((string)($row['issues_json'] ?? '[]'), true) ?: [];

            return [
                'page_uid' => (int)$row['page_uid'],
                'page_title' => (string)($metadata['page_title'] ?? ''),
                'overall_score' => (int)$row['overall_score'],
                'accessibility_score' => (int)$row['accessibility_score'],
                'seo_score' => (int)$row['seo_score'],
                'readability_score' => (int)$row['readability_score'],
                'schema_score' => (int)$row['schema_score'],
                'issue_count' => count($issues),
                'analyzed_at' => (int)$row['analyzed_at'],
                'analyzed_at_display' => $row['analyzed_at'] > 0
                    ? (new \DateTimeImmutable('@' . $row['analyzed_at']))->format('d.m.Y H:i')
                    : '',
            ];
        }, $rows);
    }

    /**
     * Count of site pages (doktype=1, visible) that have never been analysed yet.
     */
    public function getUnanalyzedPageCount(): int
    {
        $pagesQb = $this->connectionPool->getQueryBuilderForTable('pages');
        $totalPages = (int)$pagesQb
            ->count('uid')
            ->from('pages')
            ->where(
                $pagesQb->expr()->eq('doktype', $pagesQb->createNamedParameter(1, Connection::PARAM_INT)),
                $pagesQb->expr()->eq('hidden', $pagesQb->createNamedParameter(0, Connection::PARAM_INT)),
                $pagesQb->expr()->eq('deleted', $pagesQb->createNamedParameter(0, Connection::PARAM_INT)),
                $pagesQb->expr()->eq('sys_language_uid', $pagesQb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        $resultQb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_result');
        $analyzedCount = count($resultQb
            ->select('page_uid')
            ->from('tx_t3contentquality_result')
            ->where($resultQb->expr()->eq('language_uid', $resultQb->createNamedParameter(0, Connection::PARAM_INT)))
            ->groupBy('page_uid')
            ->executeQuery()
            ->fetchAllAssociative());

        return max(0, $totalPages - $analyzedCount);
    }

}

<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Controller\SchemaAdvisorController;
use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AnalysisOrchestrator;
use Woit\T3ContentQuality\Service\PageFixApplier;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class BackendModuleController extends ActionController
{
    private const FIX_TYPE_LABELS = [
        'title' => 'Page Title',
        'description' => 'Meta Description',
        'alt_text' => 'Image ALT Text',
    ];

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly SchemaAdvisorController $schemaAdvisorController,
        private readonly PageFixApplier $pageFixApplier,
        private readonly BackendUriBuilder $backendUriBuilder,
        private readonly PermissionService $permissionService,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $pageUid = $this->resolveCurrentPageUid();
        $languageUid = (int)($this->request->getQueryParams()['language'] ?? 0);
        if (!$this->permissionService->canShowPage($pageUid, $languageUid)) {
            $pageUid = 0;
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $storedResult = null;
        $schemaData = null;
        $approvedJsonLd = null;
        $schemaUrls = [];
        $checkLinksUrl = '';

        if ($pageUid > 0) {
            $checkLinksUrl = (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_check_links', ['pageUid' => $pageUid, 'languageUid' => $languageUid]);
            $storedResult = $this->analysisOrchestrator->getStoredResult($pageUid, $languageUid);
            if ($storedResult) {
                $storedResult = $this->enrichResultForView($storedResult);
                $schemaData = $storedResult['metadata']['schema'] ?? null;
                $approvedJsonLd = $this->schemaAdvisorController->getApprovedSchema($pageUid, $languageUid);
            }

            $schemaUrls = [
                'approve'   => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_schema_approve'),
                'reject'    => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_schema_reject', ['pageUid' => $pageUid, 'languageUid' => $languageUid]),
                'export'    => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_schema_export'),
                'ai_detect' => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_schema_ai_detect', ['pageUid' => $pageUid, 'languageUid' => $languageUid]),
            ];
        }

        $queryParams = $this->request->getQueryParams();
        $schemaError = '';
        $schemaMsg = '';
        $rawError = $queryParams['schemaError'] ?? '';
        $rawMsg = $queryParams['schemaMsg'] ?? '';
        if ($rawError !== '') {
            $schemaError = urldecode($rawError);
        }
        if ($rawMsg !== '') {
            $schemaMsg = urldecode($rawMsg);
        }

        $headingTree = [];
        if ($storedResult) {
            $headingTree = $this->buildHeadingTree($storedResult['metadata']['headings'] ?? []);
        }

        $trendRuns = [];
        $pendingFixes = [];
        $pendingApplyUrl = '';
        $pendingDiscardUrl = '';
        if ($pageUid > 0) {
            $history = $this->analysisOrchestrator->getHistory($pageUid, $languageUid);
            $trendRuns = $this->buildTrendRuns($history);

            $pendingFixes = $this->enrichPendingFixes($this->pageFixApplier->getPendingForPage($pageUid, $languageUid));
            $pendingApplyUrl = (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_pending_apply', ['pageUid' => $pageUid, 'languageUid' => $languageUid, 'from' => 'dashboard']);
            $pendingDiscardUrl = (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_pending_discard', ['pageUid' => $pageUid, 'languageUid' => $languageUid, 'from' => 'dashboard']);
        }

        $moduleTemplate->assignMultiple([
            'pageUid' => $pageUid,
            'languageUid' => $languageUid,
            'storedResult' => $storedResult,
            'schemaData' => $schemaData,
            'approvedJsonLd' => $approvedJsonLd,
            'schemaUrls' => $schemaUrls,
            'schemaError' => $schemaError,
            'schemaMsg' => $schemaMsg,
            'headingTree' => $headingTree,
            'trendRuns' => $trendRuns,
            'checkLinksUrl' => $checkLinksUrl,
            'pendingFixes' => $pendingFixes,
            'pendingApplyUrl' => $pendingApplyUrl,
            'pendingDiscardUrl' => $pendingDiscardUrl,
            'allPagesUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('web_t3contentquality', ['action' => 'list', 'returnId' => $pageUid]),
        ]);

        return $moduleTemplate->renderResponse('BackendModule/Index');
    }

    public function listAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $results = array_values(array_filter(
            $this->analysisOrchestrator->getAllResults(),
            fn(array $row): bool => $this->permissionService->canShowPage((int)$row['page_uid'])
        ));
        $results = array_map(function (array $row) {
            $row['overall_score_class'] = $this->scoreClass($row['overall_score']);
            $row['accessibility_score_class'] = $this->scoreClass($row['accessibility_score']);
            $row['seo_score_class'] = $this->scoreClass($row['seo_score']);
            $row['readability_score_class'] = $this->scoreClass($row['readability_score']);
            $row['schema_score_class'] = $this->scoreClass($row['schema_score']);
            $row['module_url'] = (string)$this->backendUriBuilder->buildUriFromRoute('web_t3contentquality', ['id' => $row['page_uid']]);
            return $row;
        }, $results);

        $reviewPendingFixes = [];
        $reviewTruncated = false;
        $queryParams = $this->request->getQueryParams();
        $returnId = (int)($queryParams['returnId'] ?? 0);
        $reviewPageUidsRaw = (string)($queryParams['reviewPageUids'] ?? '');
        if ($reviewPageUidsRaw !== '') {
            $reviewPageUids = $this->permissionService->filterShowablePageUids(
                array_filter(array_map('intval', explode(',', $reviewPageUidsRaw)))
            );
            $titleByUid = array_column($results, 'page_title', 'page_uid');
            $reviewTruncated = (string)($queryParams['truncated'] ?? '0') === '1';

            $reviewPendingFixes = array_map(static fn(array $row) => [
                'uid' => (int)$row['uid'],
                'page_uid' => (int)$row['page_uid'],
                'page_title' => $titleByUid[(int)$row['page_uid']] ?? ('Page #' . $row['page_uid']),
                'fix_type' => $row['fix_type'],
                'fix_type_label' => self::FIX_TYPE_LABELS[$row['fix_type']] ?? $row['fix_type'],
                'old_value' => (string)$row['old_value'],
                'new_value' => (string)$row['new_value'],
            ], $this->pageFixApplier->getPendingForPages($reviewPageUids));
        }

        $moduleTemplate->assignMultiple([
            'results' => $results,
            'unanalyzedCount' => $this->analysisOrchestrator->getUnanalyzedPageCount(),
            'bulkFixGenerateUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_bulk_fix_generate'),
            'reviewPendingFixes' => $reviewPendingFixes,
            'reviewTruncated' => $reviewTruncated,
            'pendingApplyUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_pending_apply', ['from' => 'bulk', 'returnId' => $returnId]),
            'pendingDiscardUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('t3contentquality_pending_discard', ['from' => 'bulk', 'returnId' => $returnId]),
            'backUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('web_t3contentquality', ['id' => $returnId]),
            'returnId' => $returnId,
        ]);

        return $moduleTemplate->renderResponse('BackendModule/BulkOverview');
    }

    public function analyzeAction(): ResponseInterface
    {
        $pageUid = (int)($this->request->getParsedBody()['pageUid'] ?? 0);
        $languageUid = (int)($this->request->getParsedBody()['languageUid'] ?? 0);

        if ($this->permissionService->canShowPage($pageUid, $languageUid)) {
            $this->analysisOrchestrator->analyze($pageUid, $languageUid);
        }

        return $this->redirect('index', null, null, ['id' => $pageUid, 'language' => $languageUid]);
    }

    private function resolveCurrentPageUid(): int
    {
        // Try Extbase argument first, then fall back to raw query param (TYPO3 convention: ?id=X)
        if ($this->request->hasArgument('id')) {
            return (int)$this->request->getArgument('id');
        }

        return (int)($this->request->getQueryParams()['id'] ?? 0);
    }

    private function enrichResultForView(array $result): array
    {
        $result['overall_score_class'] = $this->scoreClass((int)$result['overall_score']);
        $result['accessibility_score_class'] = $this->scoreClass((int)$result['accessibility_score']);
        $result['seo_score_class'] = $this->scoreClass((int)$result['seo_score']);
        $result['readability_score_class'] = $this->scoreClass((int)$result['readability_score']);
        $result['schema_score_class'] = $this->scoreClass((int)$result['schema_score']);

        return $result;
    }

    private function buildHeadingTree(array $headings): array
    {
        if (empty($headings)) {
            return [];
        }

        $h1Count = array_reduce($headings, static fn(int $c, array $h) => $c + ((int)($h['level'] ?? 1) === 1 ? 1 : 0), 0);

        $annotated = [];
        $prevLevel = 0;
        foreach ($headings as $heading) {
            $level = (int)($heading['level'] ?? 1);
            $skipBefore = $prevLevel > 0 && $level > $prevLevel + 1;
            $annotated[] = [
                'level' => $level,
                'text' => $heading['text'] ?? '',
                'skip_before' => $skipBefore,
                'skipped_level' => $skipBefore ? $prevLevel + 1 : 0,
                'duplicate_h1' => $level === 1 && $h1Count > 1,
                'indent' => str_repeat('  ', $level - 1),
            ];
            $prevLevel = $level;
        }

        return $annotated;
    }

    /**
     * Builds a grouped bar-chart data set: one "run" per analysis, each run
     * holding 5 category bars (height = score %). Capped to the last 10 runs
     * so the cluster stays readable. Empty when no history exists.
     *
     * @return array<int, array{date:string,bars:array<int,array{label:string,color:string,score:int}>}>
     */
    private function buildTrendRuns(array $history): array
    {
        if (empty($history)) {
            return [];
        }

        $categories = [
            'overall_score' => ['label' => 'Overall', 'color' => '#0d6efd'],
            'accessibility_score' => ['label' => 'Accessibility', 'color' => '#198754'],
            'seo_score' => ['label' => 'SEO', 'color' => '#fd7e14'],
            'readability_score' => ['label' => 'Readability', 'color' => '#6610f2'],
            'schema_score' => ['label' => 'Schema', 'color' => '#20c997'],
        ];

        $recent = array_slice($history, -10);

        $runs = [];
        foreach ($recent as $row) {
            $bars = [];
            foreach ($categories as $field => $meta) {
                $bars[] = [
                    'label' => $meta['label'],
                    'color' => $meta['color'],
                    'score' => (int)($row[$field] ?? 0),
                ];
            }

            $runs[] = [
                'date' => $row['analyzed_at'] > 0
                    ? (new \DateTimeImmutable('@' . $row['analyzed_at']))->format('d.m. H:i')
                    : '',
                'bars' => $bars,
            ];
        }

        return $runs;
    }

    /**
     * @return array<int, array{fix_type:string,fix_type_label:string,old_value:string,new_value:string}>
     */
    private function enrichPendingFixes(array $rows): array
    {
        return array_map(static fn(array $row) => [
            'fix_type' => $row['fix_type'],
            'fix_type_label' => self::FIX_TYPE_LABELS[$row['fix_type']] ?? $row['fix_type'],
            'old_value' => (string)$row['old_value'],
            'new_value' => (string)$row['new_value'],
        ], $rows);
    }

    private function scoreClass(int $score): string
    {
        return match(true) {
            $score >= 80 => 'success',
            $score >= 60 => 'warning',
            default => 'danger',
        };
    }
}

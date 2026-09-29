<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\EventListener;

use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AnalysisOrchestrator;
use Woit\T3ContentQuality\Service\PageFixApplier;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PageModuleContentListener
{
    private const LLL = 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:';

    private const FIX_TYPE_LABEL_KEYS = [
        'title' => ['panel.fixType.title', 'Page Title'],
        'description' => ['panel.fixType.description', 'Meta Description'],
        'alt_text' => ['panel.fixType.altText', 'Image ALT Text'],
    ];

    private static function translate(string $key, string $fallback): string
    {
        return LocalizationUtility::translate(self::LLL . $key) ?? $fallback;
    }

    public function __construct(
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly PageFixApplier $pageFixApplier,
        private readonly UriBuilder $uriBuilder,
        private readonly PermissionService $permissionService,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $request = $event->getRequest();
        $pageUid = (int)($request->getQueryParams()['id'] ?? 0);

        $languageUid = (int)($request->getQueryParams()['language'] ?? 0);
        if (!$this->permissionService->canShowPage($pageUid, $languageUid)) {
            return;
        }
        $storedResult = $this->analysisOrchestrator->getStoredResult($pageUid, $languageUid);
        $pendingFixes = $this->pageFixApplier->getPendingForPage($pageUid, $languageUid);

        $analyzeUrl = (string)$this->uriBuilder->buildUriFromRoute(
            't3contentquality_analyze',
            ['pageUid' => $pageUid, 'languageUid' => $languageUid]
        );

        $fixUrl = (string)$this->uriBuilder->buildUriFromRoute(
            't3contentquality_fix',
            ['pageUid' => $pageUid, 'languageUid' => $languageUid]
        );

        $pendingApplyUrl = (string)$this->uriBuilder->buildUriFromRoute(
            't3contentquality_pending_apply',
            ['pageUid' => $pageUid, 'languageUid' => $languageUid, 'from' => 'panel']
        );

        $pendingDiscardUrl = (string)$this->uriBuilder->buildUriFromRoute(
            't3contentquality_pending_discard',
            ['pageUid' => $pageUid, 'languageUid' => $languageUid, 'from' => 'panel']
        );

        $nonce = $request->getAttribute('nonce');
        $panel = $this->renderPanel($storedResult, $analyzeUrl, $fixUrl, $pageUid, $nonce, $pendingFixes, $pendingApplyUrl, $pendingDiscardUrl);
        $event->setHeaderContent($panel . $event->getHeaderContent());
    }

    private function renderPanel(
        ?array $result,
        string $analyzeUrl,
        string $fixUrl,
        int $pageUid,
        mixed $nonce = null,
        array $pendingFixes = [],
        string $pendingApplyUrl = '',
        string $pendingDiscardUrl = ''
    ): string {
        $analyzeUrlEsc = htmlspecialchars($analyzeUrl, ENT_QUOTES);
        $fixUrlEsc = htmlspecialchars($fixUrl, ENT_QUOTES);
        $collapseId = 'cq-panel-' . $pageUid;
        $btnId = 'cq-analyze-btn-' . $pageUid;

        if ($result === null) {
            $summaryHtml = sprintf(
                '<span class="text-muted small">%s</span>',
                self::translate('panel.notAnalysedYet', 'Not analysed yet.')
            );
            $btnLabel = self::translate('panel.analysePage', 'Analyse Page');
            $btnClass = 'btn-primary';
            $detailsBtn = '';
            $fixBtn = '';
            $detailsHtml = '';
        } else {
            $overall = (int)$result['overall_score'];
            $issueCount = count($result['issues'] ?? []);

            $schemaScore = (int)($result['schema_score'] ?? 0);
            $schemaMeta = $result['metadata']['schema'] ?? [];
            $schemaType = $schemaMeta['detected_type'] ?? null;

            $schemaHtml = '';
            if ($schemaType !== null) {
                $schemaHtml = sprintf(
                    '<span class="badge bg-secondary ms-1" title="Detected schema type">%s</span>'
                    . ' <span class="small text-%s" title="Schema score">%s %d</span>',
                    htmlspecialchars($schemaType, ENT_QUOTES),
                    $this->scoreClass($schemaScore),
                    self::translate('panel.schema', 'Schema:'),
                    $schemaScore
                );
            }

            $issueSummary = $issueCount > 0
                ? $issueCount . ' ' . ($issueCount > 1
                    ? self::translate('panel.issue.plural', 'issues')
                    : self::translate('panel.issue.singular', 'issue'))
                : self::translate('panel.noIssues', 'No issues');

            $qualityBadgesHtml = sprintf(
                '<div class="d-flex align-items-center gap-3 flex-wrap small mb-1">%s%s</div>',
                $this->renderQualityBadge((int)$result['readability_score'], self::translate('panel.readabilityAnalysis', 'Readability analysis')),
                $this->renderQualityBadge((int)$result['seo_score'], self::translate('panel.seoAnalysis', 'SEO analysis'))
            );

            $overallBarColor = match ($this->scoreClass($overall)) {
                'success' => '#198754',
                'warning' => '#ffc107',
                default => '#dc3545',
            };
            $overallBarWidth = min(100, max(2, $overall));
            $overallBarHtml = sprintf(
                '<span class="d-inline-flex align-items-center gap-2" style="min-width:160px">'
                . '<span style="height:8px;width:100px;background:#e9ecef;border-radius:4px;overflow:hidden">'
                . '<span style="display:block;height:100%%;width:%d%%;background:%s;border-radius:4px"></span>'
                . '</span>'
                . '<strong style="font-size:0.9rem">%d<small class="text-muted">/100</small></strong>'
                . '</span>',
                $overallBarWidth,
                $overallBarColor,
                $overall
            );

            $summaryHtml = $qualityBadgesHtml . sprintf(
                '%s'
                . '<div class="d-flex align-items-center gap-3 flex-wrap small">'
                . '<span>%s <strong class="text-%s">%d</strong></span>'
                . '<span>%s <strong class="text-%s">%d</strong></span>'
                . '<span>%s <strong class="text-%s">%d</strong></span>'
                . '%s'
                . '<span class="text-muted">%s</span>'
                . '<span class="text-muted">%s</span>'
                . '</div>',
                $overallBarHtml,
                self::translate('panel.accessibility', 'Accessibility:'), $this->scoreClass((int)$result['accessibility_score']), (int)$result['accessibility_score'],
                self::translate('panel.seo', 'SEO:'), $this->scoreClass((int)$result['seo_score']), (int)$result['seo_score'],
                self::translate('panel.readability', 'Readability:'), $this->scoreClass((int)$result['readability_score']), (int)$result['readability_score'],
                $schemaHtml,
                $issueSummary,
                htmlspecialchars($result['analyzed_at_display'] ?? '', ENT_QUOTES)
            );

            $btnLabel = self::translate('panel.reanalyse', 'Reanalyse');
            $btnClass = 'btn-outline-secondary';
            $detailsBtn = sprintf(
                '<button class="btn btn-sm btn-outline-secondary" type="button"'
                . ' data-bs-toggle="collapse" data-bs-target="#%s" aria-expanded="false">'
                . '%s <span class="caret">▾</span>'
                . '</button>',
                $collapseId,
                self::translate('panel.details', 'Details')
            );
            $fixBtn = '';
            $detailsHtml = $this->renderDetails($result, $collapseId, $fixUrl);
        }

        $nonceAttr = '';
        if ($nonce !== null) {
            $nonceAttr = ' nonce="' . htmlspecialchars((string)$nonce, ENT_QUOTES) . '"';
        }

        $loaderScript = sprintf(
            '<script%s>(function(){var b=document.getElementById(%s);if(b)b.addEventListener("click",function(){'
            . 'this.innerHTML="<span class=\"spinner-border spinner-border-sm me-1\" role=\"status\"></span>%s";'
            . 'this.classList.add("disabled","pe-none");});})();</script>',
            $nonceAttr,
            json_encode($btnId),
            addslashes(self::translate('panel.analysing', 'Analysing…'))
        );

        $pendingHtml = $this->renderPendingFixBlock($pendingFixes, $pendingApplyUrl, $pendingDiscardUrl);

        return sprintf(
            '<div class="card mb-3 border-secondary-subtle">'
            . '<div class="card-body py-2 px-3">'
            . '<div class="d-flex align-items-center flex-wrap gap-3 row-gap-2">'
            . '<strong class="text-nowrap">%s</strong>'
            . '%s'
            . '<div class="ms-auto d-flex gap-2 flex-shrink-0">%s%s<a id="%s" href="%s" class="btn btn-sm %s">%s</a></div>'
            . '</div>'
            . '%s'
            . '%s'
            . '</div></div>%s',
            self::translate('panel.title', 'Content Quality'),
            $summaryHtml,
            $detailsBtn,
            $fixBtn,
            htmlspecialchars($btnId, ENT_QUOTES),
            $analyzeUrlEsc,
            $btnClass,
            $btnLabel,
            $pendingHtml,
            $detailsHtml,
            $loaderScript
        );
    }

    private function renderPendingFixBlock(array $pendingFixes, string $applyUrl, string $discardUrl): string
    {
        if (empty($pendingFixes)) {
            return '';
        }

        $rows = '';
        foreach ($pendingFixes as $fix) {
            [$labelKey, $labelFallback] = self::FIX_TYPE_LABEL_KEYS[$fix['fix_type']] ?? [null, $fix['fix_type']];
            $label = $labelKey !== null ? self::translate($labelKey, $labelFallback) : $labelFallback;
            $rows .= sprintf(
                '<div class="mb-2"><div class="small fw-semibold">%s</div>'
                . '<div class="small text-muted text-decoration-line-through">%s</div>'
                . '<div class="small text-success">%s</div></div>',
                htmlspecialchars($label, ENT_QUOTES),
                htmlspecialchars((string)$fix['old_value'], ENT_QUOTES),
                htmlspecialchars((string)$fix['new_value'], ENT_QUOTES)
            );
        }

        return sprintf(
            '<hr class="my-2"><div class="alert alert-warning py-2 px-3 mb-0">'
            . '<strong class="small d-block mb-2">%s</strong>'
            . '%s'
            . '<div class="d-flex gap-2 mt-2">'
            . '<a href="%s" class="btn btn-sm btn-success">%s</a>'
            . '<a href="%s" class="btn btn-sm btn-outline-secondary">%s</a>'
            . '</div></div>',
            self::translate('panel.aiSuggestedChanges', '✨ AI Suggested Changes (not saved yet)'),
            $rows,
            htmlspecialchars($applyUrl, ENT_QUOTES),
            self::translate('panel.apply', 'Apply'),
            htmlspecialchars($discardUrl, ENT_QUOTES),
            self::translate('panel.discard', 'Discard')
        );
    }

    private function resolveFixType(array $issue): ?string
    {
        $message = strtolower($issue['message'] ?? '');
        $code    = strtolower($issue['code'] ?? '');

        if (str_contains($message, 'alt text') || str_contains($message, 'alt-text') || str_contains($code, 'alt')) {
            return 'alt_text';
        }
        if (str_contains($message, 'meta description') || str_contains($message, 'description')) {
            return 'description';
        }
        if (str_contains($message, 'title')) {
            return 'title';
        }

        return null;
    }

    private function renderDetails(array $result, string $collapseId, string $fixUrl): string
    {
        $issues = $result['issues'] ?? [];
        $suggestions = $result['suggestions'] ?? [];
        $metadata = $result['metadata'] ?? [];

        $sections = '<hr class="my-2">';

        // SERP Preview
        $sections .= $this->renderSerpPreview($metadata);

        // Accessibility breakdown
        $sections .= $this->renderAccessibilityBreakdown($result);

        // Flesch score
        $sections .= $this->renderFleschScore($metadata);

        // Issues table
        $issueRows = '';
        foreach ($issues as $issue) {
            $severityClass = match ($issue['severity'] ?? '') {
                'error' => 'danger',
                'warning' => 'warning',
                default => 'info',
            };
            $fixType = $this->resolveFixType($issue);
            $fixCell = '<span class="text-muted">&mdash;</span>';
            if ($fixType !== null) {
                $fixCell = sprintf(
                    '<a href="%s" class="btn btn-sm btn-warning py-0 px-2" style="font-size:0.75rem" title="%s">✨ %s</a>',
                    htmlspecialchars($fixUrl . '&fixType=' . $fixType, ENT_QUOTES),
                    self::translate('panel.fixWithAi', 'Fix with AI'),
                    self::translate('panel.fixButton', 'Fix')
                );
            }
            $issueRows .= sprintf(
                '<tr>'
                . '<td style="width:120px"><span class="badge bg-secondary text-capitalize">%s</span></td>'
                . '<td style="width:90px"><span class="badge bg-%s text-capitalize">%s</span></td>'
                . '<td>%s</td>'
                . '<td><small class="text-muted">%s</small></td>'
                . '<td style="width:80px" class="text-center">%s</td>'
                . '</tr>',
                htmlspecialchars($issue['category'] ?? '', ENT_QUOTES),
                $severityClass,
                htmlspecialchars($issue['severity'] ?? '', ENT_QUOTES),
                htmlspecialchars($issue['message'] ?? '', ENT_QUOTES),
                htmlspecialchars($issue['suggestion'] ?? '', ENT_QUOTES),
                $fixCell
            );
        }

        $sections .= '<div class="mt-3"><strong class="small d-block mb-2">' . self::translate('panel.issuesHeading', 'Issues') . '</strong>';
        $sections .= $issueRows !== ''
            ? '<div class="table-responsive"><table class="table table-sm table-striped mb-0">'
              . sprintf(
                  '<thead class="table-light"><tr><th style="width:120px">%s</th><th style="width:90px">%s</th><th>%s</th><th>%s</th><th style="width:80px" class="text-center">%s</th></tr></thead>',
                  self::translate('panel.category', 'Category'),
                  self::translate('panel.severity', 'Severity'),
                  self::translate('panel.issueColumn', 'Issue'),
                  self::translate('panel.suggestion', 'Suggestion'),
                  self::translate('panel.action', 'Action')
              )
              . '<tbody>' . $issueRows . '</tbody></table></div>'
            : '<p class="text-muted small mb-0">' . self::translate('panel.noIssuesFound', 'No issues found.') . '</p>';
        $sections .= '</div>';

        // AI suggestions
        if (!empty($suggestions)) {
            $items = '';
            foreach ($suggestions as $s) {
                $items .= '<li class="mb-1">' . htmlspecialchars((string)$s, ENT_QUOTES) . '</li>';
            }
            $provider = trim(($result['provider_used'] ?? '') . ' / ' . ($result['model_used'] ?? ''), ' /');
            $sections .= '<div class="mt-3">'
                . '<strong class="small">' . self::translate('panel.aiSuggestions', 'AI Suggestions') . '</strong>'
                . ($provider !== '' ? ' <small class="text-muted">(' . htmlspecialchars($provider, ENT_QUOTES) . ')</small>' : '')
                . '<ol class="mt-1 mb-0 small">' . $items . '</ol>'
                . '</div>';
        }

        return sprintf('<div class="collapse" id="%s">%s</div>', $collapseId, $sections);
    }

    private function renderSerpPreview(array $metadata): string
    {
        $seoTitle = trim((string)($metadata['seo_title'] ?? ''));
        $title = $seoTitle !== '' ? $seoTitle : ($metadata['page_title'] ?? '');
        $desc = $metadata['meta_description'] ?? '';

        if ($title === '' && $desc === '') {
            return '';
        }

        $titleLen = mb_strlen($title);
        $descLen = mb_strlen($desc);

        $titleClass = $titleLen > 70 ? 'danger' : ($titleLen >= 30 ? 'success' : 'warning');
        $descClass = $descLen > 160 ? 'danger' : ($descLen >= 120 ? 'success' : ($descLen > 0 ? 'warning' : 'danger'));

        $titleDisplay = $titleLen > 65
            ? htmlspecialchars(mb_substr($title, 0, 65), ENT_QUOTES) . '…'
            : htmlspecialchars($title, ENT_QUOTES);

        $descDisplay = $descLen > 160
            ? htmlspecialchars(mb_substr($desc, 0, 157), ENT_QUOTES) . '…'
            : htmlspecialchars($desc, ENT_QUOTES);

        $siteHost = $metadata['site_host'] ?? '';
        $siteBaseUrl = $metadata['site_base_url'] ?? '';
        $siteName = $siteHost ?: 'your-site.com';
        $siteInitial = mb_strtoupper(mb_substr($siteName, 0, 1));

        // Google breadcrumb: host › Page Title
        $breadcrumb = htmlspecialchars($siteHost, ENT_QUOTES);
        if ($title !== '') {
            $breadcrumb .= ' › ' . htmlspecialchars(mb_substr($title, 0, 40), ENT_QUOTES);
        }

        $urlDisplay = $siteBaseUrl !== ''
            ? htmlspecialchars($siteBaseUrl, ENT_QUOTES)
            : 'https://' . htmlspecialchars($siteName, ENT_QUOTES);

        $urlBreadcrumb = $siteHost !== '' ? htmlspecialchars($siteHost, ENT_QUOTES) . ' ›' : '';

        return '<div class="mt-3">'
            . '<strong class="small d-block mb-2">' . self::translate('panel.serpPreview', 'SERP Preview') . '</strong>'
            . '<div style="background:#fff;padding:12px 16px;max-width:600px;font-family:arial,sans-serif">'
            . sprintf(
                '<div style="font-size:13px;color:#007526;margin-bottom:2px">%s <span style="font-size:10px">&#9660;</span></div>',
                $urlBreadcrumb
            )
            . sprintf(
                '<div style="font-size:18px;color:#1a0dab;font-weight:normal;line-height:1.3;margin-bottom:3px">%s</div>',
                $titleDisplay
            )
            . sprintf(
                '<div style="font-size:13px;color:#545454;line-height:1.4">%s</div>',
                $descDisplay !== '' ? $descDisplay : '<em style="color:#aaa">' . self::translate('panel.noMetaDescription', 'No meta description.') . '</em>'
            )
            . '</div>'
            . sprintf(
                '<div class="d-flex gap-4 mt-2 small">'
                . '<span>%s <strong class="text-%s">%d %s</strong></span>'
                . '<span>%s <strong class="text-%s">%d %s</strong></span>'
                . '</div>',
                self::translate('panel.titleLabel', 'Title:'), $titleClass, $titleLen, self::translate('panel.charsOf60', '/ 60 chars'),
                self::translate('panel.descriptionLabel', 'Description:'), $descClass, $descLen, self::translate('panel.charsOf160', '/ 160 chars')
            )
            . '</div>';
    }

    private function renderAccessibilityBreakdown(array $result): string
    {
        $score = (int)($result['accessibility_score'] ?? 0);
        $scoreClass = $this->scoreClass($score);

        $accessibilityIssues = array_values(array_filter(
            $result['issues'] ?? [],
            static fn (array $issue): bool => ($issue['category'] ?? '') === 'accessibility'
        ));

        $itemsHtml = '';
        foreach ($accessibilityIssues as $issue) {
            $severityClass = match ($issue['severity'] ?? '') {
                'error' => 'danger',
                'warning' => 'warning',
                default => 'info',
            };
            $itemsHtml .= sprintf(
                '<li class="mb-2"><span class="badge bg-%s text-capitalize me-1">%s</span>%s'
                . '<div class="text-muted small">%s</div></li>',
                $severityClass,
                htmlspecialchars($issue['severity'] ?? '', ENT_QUOTES),
                htmlspecialchars($issue['message'] ?? '', ENT_QUOTES),
                htmlspecialchars($issue['suggestion'] ?? '', ENT_QUOTES)
            );
        }

        $body = $itemsHtml !== ''
            ? '<ul class="mt-2 mb-0 ps-3" style="max-width:600px">' . $itemsHtml . '</ul>'
            : '<p class="text-muted small mt-2 mb-0">' . self::translate('panel.noAccessibilityIssues', 'No accessibility issues found.') . '</p>';

        return '<div class="mt-3">'
            . '<strong class="small d-block mb-2">' . self::translate('panel.accessibilityHeading', 'Accessibility') . '</strong>'
            . '<div class="d-flex align-items-center gap-3">'
            . sprintf('<span class="badge bg-%s fs-6 px-3">%d / 100</span>', $scoreClass, $score)
            . '</div>'
            . $body
            . '</div>';
    }

    private function renderFleschScore(array $metadata): string
    {
        if (!isset($metadata['flesch_score'])) {
            return '';
        }

        $score = (float)$metadata['flesch_score'];
        $scoreClass = $score >= 60 ? 'success' : ($score >= 30 ? 'warning' : 'danger');

        $label = match(true) {
            $score >= 70 => self::translate('panel.readingEase.easy', 'Easy to read'),
            $score >= 60 => self::translate('panel.readingEase.fairlyEasy', 'Fairly easy'),
            $score >= 50 => self::translate('panel.readingEase.moderate', 'Moderate'),
            $score >= 30 => self::translate('panel.readingEase.difficult', 'Difficult'),
            default      => self::translate('panel.readingEase.veryDifficult', 'Very difficult'),
        };

        $barWidth = min(100, max(2, (int)$score));
        $barColor = match ($scoreClass) {
            'success' => '#198754',
            'warning' => '#ffc107',
            default   => '#dc3545',
        };

        return '<div class="mt-3">'
            . sprintf(
                '<strong class="small d-block mb-2">%s <small class="text-muted fw-normal">%s</small></strong>',
                self::translate('panel.fleschReadingEase', 'Flesch Reading Ease'),
                self::translate('panel.germanAmstad', '(German Amstad)')
            )
            . '<div class="d-flex align-items-center gap-3">'
            . sprintf('<span class="badge bg-%s fs-6 px-3">%.0f / 100</span>', $scoreClass, $score)
            . sprintf('<span class="small text-%s">%s</span>', $scoreClass, $label)
            . '</div>'
            . '<div class="mt-2" style="height:8px;max-width:300px;background:#e9ecef;border-radius:4px;overflow:hidden">'
            . sprintf('<div style="height:100%%;width:%d%%;background:%s;border-radius:4px"></div>', $barWidth, $barColor)
            . '</div>'
            . sprintf(
                '<div class="d-flex gap-3 mt-1" style="max-width:300px;font-size:11px;color:#6c757d">'
                . '<span>%s</span><span class="ms-auto">%s</span>'
                . '</div>',
                self::translate('panel.hard', '0 Hard'),
                self::translate('panel.easy', '100 Easy')
            )
            . '</div>';
    }

    private function renderQualityBadge(int $score, string $label): string
    {
        [$emoji, $labelKey, $fallback] = match (true) {
            $score >= 80 => ['🟢', 'panel.quality.good', 'Good'],
            $score >= 60 => ['🟡', 'panel.quality.fair', 'Fair'],
            default => ['🔴', 'panel.quality.poor', 'Poor'],
        };

        return sprintf(
            '<span class="d-inline-flex align-items-center gap-1">%s %s: <strong>%s</strong></span>',
            $emoji,
            htmlspecialchars($label, ENT_QUOTES),
            self::translate($labelKey, $fallback)
        );
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

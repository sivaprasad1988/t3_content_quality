<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Analyzer\AiPageFixer;
use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Woit\T3ContentQuality\Service\AnalysisOrchestrator;
use Woit\T3ContentQuality\Service\PageContentExtractor;
use Woit\T3ContentQuality\Service\PageFixApplier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\RedirectResponse;

class FixPageController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiPageFixer $aiPageFixer,
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly PageContentExtractor $contentExtractor,
        private readonly PageFixApplier $pageFixApplier,
        private readonly AiSettingsFactory $aiSettingsFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly PermissionService $permissionService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $pageUid = (int)($params['pageUid'] ?? 0);
        $languageUid = (int)($params['languageUid'] ?? 0);

        $redirectUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_layout',
            ['id' => $pageUid]
        );

        $fixType = (string)($params['fixType'] ?? 'all');
        $allowed = $fixType === 'alt_text'
            ? $this->permissionService->canEditPageContent($pageUid, $languageUid)
            : $this->permissionService->canEditPage($pageUid, $languageUid);
        if ($pageUid <= 0 || !$allowed) {
            return new RedirectResponse($redirectUrl, 303);
        }

        $aiSettings = $this->aiSettingsFactory->create();

        if (!$aiSettings->isUsable()) {
            $this->logger?->warning('AI fix skipped: no API key configured');
            return new RedirectResponse($redirectUrl, 303);
        }

        $storedResult = $this->analysisOrchestrator->getStoredResult($pageUid, $languageUid);
        $issues = $storedResult['issues'] ?? [];
        $pageData = $this->contentExtractor->extractFromPage($pageUid, $languageUid);

        if ($fixType === 'alt_text') {
            $imagesWithoutAlt = $this->contentExtractor->getImagesMissingAlt($pageUid, $languageUid);
            if (!empty($imagesWithoutAlt)) {
                $altFixes = $this->aiPageFixer->generateAltTexts($imagesWithoutAlt, $pageData, $aiSettings);
                if (!empty($altFixes)) {
                    $images = array_map(static fn(array $img) => ['uid' => (int)$img['uid'], 'current_alt' => ''], $imagesWithoutAlt);
                    $this->pageFixApplier->storePendingAltTextFixes($pageUid, $languageUid, $altFixes, $images);
                }
            }
        } else {
            $fixes = $this->aiPageFixer->generateFixes($pageData, $issues, $aiSettings);
            if (!empty($fixes)) {
                if ($fixType !== 'all') {
                    $fixes = array_intersect_key($fixes, [$fixType => true]);
                }
                $currentValues = [
                    'title' => $pageData['page_title'] ?? '',
                    'description' => $pageData['meta_description'] ?? '',
                ];
                $this->pageFixApplier->storePendingPageFieldFixes($pageUid, $languageUid, $fixes, $currentValues);
            }
        }

        return new RedirectResponse($redirectUrl, 303);
    }

}

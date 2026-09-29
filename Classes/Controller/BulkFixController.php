<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Analyzer\AiPageFixer;
use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AiSettings;
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

class BulkFixController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const MAX_PAGES_PER_BATCH = 15;

    public function __construct(
        private readonly AiPageFixer $aiPageFixer,
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly PageContentExtractor $contentExtractor,
        private readonly PageFixApplier $pageFixApplier,
        private readonly AiSettingsFactory $aiSettingsFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Generates AI fix proposals (pending, not written) for up to 15 selected pages,
     * then redirects back to the bulk overview module with the affected page uids so
     * it can render the review table for this batch.
     */
    public function generateAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $pageUids = array_values(array_filter(array_map('intval', (array)($body['pageUids'] ?? []))));
        $fixType = (string)($body['fixType'] ?? 'title');
        $returnId = (int)($body['returnId'] ?? 0);
        $pageUids = array_values(array_filter(
            $pageUids,
            fn(int $pageUid): bool => $this->permissionService->canApplyFixType($fixType, $pageUid)
        ));

        $truncated = count($pageUids) > self::MAX_PAGES_PER_BATCH;
        $pageUids = array_slice($pageUids, 0, self::MAX_PAGES_PER_BATCH);

        $aiSettings = $this->aiSettingsFactory->create();

        if (!empty($pageUids) && $aiSettings->isUsable()) {
            foreach ($pageUids as $pageUid) {
                $this->generateForPage($pageUid, $fixType, $aiSettings);
            }
        }

        $url = (string)$this->uriBuilder->buildUriFromRoute('web_t3contentquality', [
            'action' => 'list',
            'reviewPageUids' => implode(',', $pageUids),
            'truncated' => $truncated ? '1' : '0',
            'returnId' => $returnId,
        ]);

        return new RedirectResponse($url, 303);
    }

    private function generateForPage(int $pageUid, string $fixType, AiSettings $aiSettings): void
    {
        $languageUid = 0;

        try {
            if ($fixType === 'alt_text') {
                $imagesWithoutAlt = $this->contentExtractor->getImagesMissingAlt($pageUid, $languageUid);
                if (empty($imagesWithoutAlt)) {
                    return;
                }
                $pageData = $this->contentExtractor->extractFromPage($pageUid, $languageUid);
                $altFixes = $this->aiPageFixer->generateAltTexts($imagesWithoutAlt, $pageData, $aiSettings);
                if (empty($altFixes)) {
                    return;
                }
                $images = array_map(static fn(array $img) => ['uid' => (int)$img['uid'], 'current_alt' => ''], $imagesWithoutAlt);
                $this->pageFixApplier->storePendingAltTextFixes($pageUid, $languageUid, $altFixes, $images);
                return;
            }

            $storedResult = $this->analysisOrchestrator->getStoredResult($pageUid, $languageUid);
            $issues = $storedResult['issues'] ?? [];
            $pageData = $this->contentExtractor->extractFromPage($pageUid, $languageUid);

            $fixes = $this->aiPageFixer->generateFixes($pageData, $issues, $aiSettings);
            if (empty($fixes)) {
                return;
            }
            $fixes = array_intersect_key($fixes, [$fixType => true]);
            $currentValues = [
                'title' => $pageData['page_title'] ?? '',
                'description' => $pageData['meta_description'] ?? '',
            ];
            $this->pageFixApplier->storePendingPageFieldFixes($pageUid, $languageUid, $fixes, $currentValues);
        } catch (\Throwable $e) {
            $this->logger?->error('Batch fix generation failed for page', ['pageUid' => $pageUid, 'exception' => $e->getMessage()]);
        }
    }

}

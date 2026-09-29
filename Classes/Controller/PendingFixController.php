<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AnalysisOrchestrator;
use Woit\T3ContentQuality\Service\PageFixApplier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\RedirectResponse;

class PendingFixController
{
    public function __construct(
        private readonly PageFixApplier $pageFixApplier,
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly UriBuilder $uriBuilder,
        private readonly PermissionService $permissionService,
    ) {}

    /** Applies pending fixes (page-scoped, or a specific list of pending-fix uids for batch review) and re-analyzes affected pages. */
    public function applyAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = array_merge($request->getQueryParams(), (array)$request->getParsedBody());

        $affected = $this->pageFixApplier->applyRows($this->getPermittedRows($params));
        foreach ($affected as $pageLanguage) {
            $this->analysisOrchestrator->analyze($pageLanguage['pageUid'], $pageLanguage['languageUid']);
        }

        return $this->redirectFrom($params);
    }

    /** Discards pending fixes (page-scoped, or a specific list of pending-fix uids). */
    public function discardAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = array_merge($request->getQueryParams(), (array)$request->getParsedBody());

        $this->pageFixApplier->discardRows($this->getPermittedRows($params));

        return $this->redirectFrom($params);
    }

    /** Pending rows addressed by the request that the current user may edit. */
    private function getPermittedRows(array $params): array
    {
        $uids = $this->extractUids($params);
        if (!empty($uids)) {
            $rows = $this->pageFixApplier->getPendingByUids($uids);
        } else {
            $pageUid = (int)($params['pageUid'] ?? 0);
            $rows = $pageUid > 0
                ? $this->pageFixApplier->getPendingForPage($pageUid, (int)($params['languageUid'] ?? 0))
                : [];
        }

        return array_values(array_filter(
            $rows,
            fn(array $row): bool => $this->permissionService->canApplyFixType(
                (string)$row['fix_type'],
                (int)$row['page_uid'],
                (int)$row['language_uid']
            )
        ));
    }

    private function extractUids(array $params): array
    {
        $raw = $params['uids'] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        return is_array($raw) ? array_map('intval', $raw) : [];
    }

    private function redirectFrom(array $params): ResponseInterface
    {
        $pageUid = (int)($params['pageUid'] ?? 0);
        $from = (string)($params['from'] ?? 'dashboard');

        $url = match ($from) {
            'panel' => (string)$this->uriBuilder->buildUriFromRoute('web_layout', ['id' => $pageUid]),
            'bulk' => (string)$this->uriBuilder->buildUriFromRoute('web_t3contentquality', ['action' => 'list', 'returnId' => (int)($params['returnId'] ?? 0)]),
            default => (string)$this->uriBuilder->buildUriFromRoute('web_t3contentquality', ['id' => $pageUid]),
        };

        return new RedirectResponse($url, 303);
    }
}

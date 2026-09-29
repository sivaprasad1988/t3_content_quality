<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AnalysisOrchestrator;
use Woit\T3ContentQuality\Service\LinkChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\RedirectResponse;

class LinkCheckController
{
    public function __construct(
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly LinkChecker $linkChecker,
        private readonly UriBuilder $uriBuilder,
        private readonly PermissionService $permissionService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $pageUid = (int)($params['pageUid'] ?? 0);
        $languageUid = (int)($params['languageUid'] ?? 0);

        if ($this->permissionService->canShowPage($pageUid, $languageUid)) {
            $this->analysisOrchestrator->checkLinks($pageUid, $languageUid, $this->linkChecker);
        }

        $redirectUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_t3contentquality',
            ['id' => $pageUid, 'language' => $languageUid]
        );

        return new RedirectResponse($redirectUrl, 303);
    }
}

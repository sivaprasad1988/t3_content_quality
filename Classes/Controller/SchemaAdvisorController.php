<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Domain\Repository\ApprovedSchemaRepository;
use Woit\T3ContentQuality\Middleware\JsonLdInjector;
use Woit\T3ContentQuality\Security\PermissionService;
use Woit\T3ContentQuality\Service\AnalysisOrchestrator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;

class SchemaAdvisorController
{
    public function __construct(
        private readonly AnalysisOrchestrator $analysisOrchestrator,
        private readonly ConnectionPool $connectionPool,
        private readonly UriBuilder $uriBuilder,
        private readonly PermissionService $permissionService,
        private readonly ApprovedSchemaRepository $approvedSchemaRepository,
    ) {}

    /** Run AI-powered schema detection for a page and patch the stored result. */
    public function aiDetectAction(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $pageUid = (int)($query['pageUid'] ?? 0);
        $languageUid = (int)($query['languageUid'] ?? 0);

        $isError = false;
        $msg = '';

        if ($this->permissionService->canShowPage($pageUid, $languageUid)) {
            try {
                $result = $this->analysisOrchestrator->analyzeSchemaWithAi($pageUid, $languageUid);
                if (str_starts_with($result, 'error:')) {
                    $isError = true;
                    $msg = substr($result, 6);
                } else {
                    $msg = substr($result, 3); // strip 'ok:' prefix
                }
            } catch (\Throwable $e) {
                $isError = true;
                $msg = 'AI schema detection failed: ' . $e->getMessage();
            }
        }

        return $this->redirectToModule($pageUid, $languageUid, $isError ? $msg : null, $isError ? null : $msg);
    }

    /** Store the generated JSON-LD as approved for a page. */
    public function approveAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = array_merge($request->getQueryParams(), (array)$request->getParsedBody());
        $pageUid = (int)($params['pageUid'] ?? 0);
        $languageUid = (int)($params['languageUid'] ?? 0);
        $jsonld = trim((string)($params['jsonld'] ?? ''));

        if ($jsonld !== '' && $this->permissionService->canEditPage($pageUid, $languageUid)) {
            // Validate and normalise JSON before storing
            $decoded = json_decode($jsonld, true);
            $normalized = JsonLdInjector::encodeForScriptTag($jsonld);
            if (is_array($decoded) && $normalized !== null) {
                $jsonld = $normalized;
                $schemaType = is_string($decoded['@type'] ?? null) ? $decoded['@type'] : '';
                $connection = $this->connectionPool->getConnectionForTable('tx_t3contentquality_schema');

                $connection->delete(
                    'tx_t3contentquality_schema',
                    ['page_uid' => $pageUid, 'language_uid' => $languageUid],
                    [Connection::PARAM_INT, Connection::PARAM_INT]
                );

                $connection->insert('tx_t3contentquality_schema', [
                    'pid' => $pageUid,
                    'page_uid' => $pageUid,
                    'language_uid' => $languageUid,
                    'schema_type' => $schemaType,
                    'jsonld_json' => $jsonld,
                    'approved' => 1,
                    'approved_at' => time(),
                ]);
            }
        }

        return $this->redirectToModule($pageUid, $languageUid);
    }

    /** Remove the approved schema for a page. */
    public function rejectAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $pageUid = (int)($params['pageUid'] ?? 0);
        $languageUid = (int)($params['languageUid'] ?? 0);

        if ($this->permissionService->canEditPage($pageUid, $languageUid)) {
            $this->connectionPool->getConnectionForTable('tx_t3contentquality_schema')->delete(
                'tx_t3contentquality_schema',
                ['page_uid' => $pageUid, 'language_uid' => $languageUid],
                [Connection::PARAM_INT, Connection::PARAM_INT]
            );
        }

        return $this->redirectToModule($pageUid, $languageUid);
    }

    /** Export schema audit as CSV. */
    public function exportAction(ServerRequestInterface $request): ResponseInterface
    {
        $rows = $this->connectionPool
            ->getQueryBuilderForTable('tx_t3contentquality_result')
            ->select('r.page_uid', 'r.overall_score', 'r.schema_score', 'r.metadata_json', 'r.analyzed_at')
            ->from('tx_t3contentquality_result', 'r')
            ->orderBy('r.page_uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $approvedUids = $this->approvedSchemaRepository->findApprovedPageUids();

        $csv = "page_uid,schema_type,schema_score,overall_score,approved,analyzed_at\n";
        foreach ($rows as $row) {
            if (!$this->permissionService->canShowPage((int)$row['page_uid'])) {
                continue;
            }
            $metadata = json_decode((string)($row['metadata_json'] ?? '[]'), true) ?: [];
            $schemaType = (string)($metadata['schema']['detected_type'] ?? '');
            $analyzedAt = $row['analyzed_at'] > 0
                ? (new \DateTimeImmutable('@' . $row['analyzed_at']))->format('Y-m-d H:i:s')
                : '';

            $csv .= implode(',', [
                $row['page_uid'],
                $schemaType,
                $row['schema_score'],
                $row['overall_score'],
                in_array((int)$row['page_uid'], $approvedUids, true) ? '1' : '0',
                $analyzedAt,
            ]) . "\n";
        }

        $response = new Response();
        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="schema-audit-' . date('Y-m-d') . '.csv"');
    }

    public function getApprovedSchema(int $pageUid, int $languageUid = 0): ?string
    {
        return $this->approvedSchemaRepository->findJsonLd($pageUid, $languageUid);
    }

    private function redirectToModule(int $pageUid, int $languageUid, ?string $error = null, ?string $msg = null): ResponseInterface
    {
        $params = ['id' => $pageUid, 'language' => $languageUid];
        if ($error !== null) {
            $params['schemaError'] = urlencode($error);
        }
        if ($msg !== null) {
            $params['schemaMsg'] = urlencode($msg);
        }
        $url = (string)$this->uriBuilder->buildUriFromRoute('web_t3contentquality', $params);
        return new RedirectResponse($url, 303);
    }
}

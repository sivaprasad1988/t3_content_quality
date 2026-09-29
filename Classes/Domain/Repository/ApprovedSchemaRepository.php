<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Read access to approved JSON-LD. Kept free of backend dependencies because
 * the frontend middleware uses it on every request.
 */
class ApprovedSchemaRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function findJsonLd(int $pageUid, int $languageUid = 0): ?string
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_schema');
        $jsonLd = $qb->select('jsonld_json')
            ->from('tx_t3contentquality_schema')
            ->where(
                $qb->expr()->eq('page_uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('approved', $qb->createNamedParameter(1, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $jsonLd !== false ? (string)$jsonLd : null;
    }

    /**
     * @return int[]
     */
    public function findApprovedPageUids(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_schema');
        $uids = $qb->select('page_uid')
            ->from('tx_t3contentquality_schema')
            ->where($qb->expr()->eq('approved', $qb->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $uids);
    }
}

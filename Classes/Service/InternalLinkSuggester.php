<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class InternalLinkSuggester
{
    private const MAX_CANDIDATES = 50;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array<int, array{uid:int,title:string,slug:string}>
     */
    public function getCandidatePages(int $excludePageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');

        $qb->select('uid', 'title', 'slug')
            ->from('pages')
            ->where(
                $qb->expr()->eq('doktype', $qb->createNamedParameter(1, Connection::PARAM_INT)),
                $qb->expr()->eq('hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->neq('uid', $qb->createNamedParameter($excludePageUid, Connection::PARAM_INT))
            )
            ->setMaxResults(self::MAX_CANDIDATES);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(static fn(array $row) => [
            'uid' => (int)$row['uid'],
            'title' => (string)$row['title'],
            'slug' => (string)$row['slug'],
        ], $rows);
    }
}

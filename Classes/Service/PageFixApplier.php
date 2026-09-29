<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Shared pending-fix storage/apply logic used by both the single-page fix
 * flow and the batch fix flow. Nothing is written to `pages` / `sys_file_reference`
 * until applyRows() is called — generation only stores a
 * proposal in tx_t3contentquality_pending_fix for editor review.
 */
class PageFixApplier implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const PAGE_FIELD_FIX_TYPES = ['title', 'description'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Stores title/description fixes as pending rows, replacing any existing
     * pending rows of the same type for this page/language. Skips fields where
     * the AI-proposed value equals the current one or is empty.
     */
    public function storePendingPageFieldFixes(int $pageUid, int $languageUid, array $fixes, array $currentValues): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_t3contentquality_pending_fix');

        foreach (self::PAGE_FIELD_FIX_TYPES as $fixType) {
            if (!isset($fixes[$fixType]) || !is_string($fixes[$fixType]) || $fixes[$fixType] === '') {
                continue;
            }
            $newValue = $fixes[$fixType];
            $oldValue = (string)($currentValues[$fixType] ?? '');
            if ($newValue === $oldValue) {
                continue;
            }

            $connection->delete(
                'tx_t3contentquality_pending_fix',
                ['page_uid' => $pageUid, 'language_uid' => $languageUid, 'fix_type' => $fixType, 'target_ref' => 0],
                [Connection::PARAM_INT, Connection::PARAM_INT, Connection::PARAM_STR, Connection::PARAM_INT]
            );

            $connection->insert('tx_t3contentquality_pending_fix', [
                'pid' => $pageUid,
                'page_uid' => $pageUid,
                'language_uid' => $languageUid,
                'fix_type' => $fixType,
                'target_ref' => 0,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'created_at' => time(),
            ]);
        }
    }

    /**
     * Stores alt-text fixes as pending rows, one per sys_file_reference uid.
     *
     * @param array<int,string> $altFixes uid => new alt text
     * @param array<int,array{uid:int,current_alt:string}> $images
     */
    public function storePendingAltTextFixes(int $pageUid, int $languageUid, array $altFixes, array $images): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_t3contentquality_pending_fix');
        $currentByUid = array_column($images, 'current_alt', 'uid');

        foreach ($altFixes as $refUid => $newValue) {
            if ($newValue === '') {
                continue;
            }
            $oldValue = (string)($currentByUid[$refUid] ?? '');
            if ($newValue === $oldValue) {
                continue;
            }

            $connection->delete(
                'tx_t3contentquality_pending_fix',
                ['fix_type' => 'alt_text', 'target_ref' => (int)$refUid],
                [Connection::PARAM_STR, Connection::PARAM_INT]
            );

            $connection->insert('tx_t3contentquality_pending_fix', [
                'pid' => $pageUid,
                'page_uid' => $pageUid,
                'language_uid' => $languageUid,
                'fix_type' => 'alt_text',
                'target_ref' => (int)$refUid,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'created_at' => time(),
            ]);
        }
    }

    public function getPendingForPage(int $pageUid, int $languageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_pending_fix');
        return $qb->select('*')
            ->from('tx_t3contentquality_pending_fix')
            ->where(
                $qb->expr()->eq('page_uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int[] $pageUids
     */
    public function getPendingForPages(array $pageUids): array
    {
        if (empty($pageUids)) {
            return [];
        }
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_pending_fix');
        return $qb->select('*')
            ->from('tx_t3contentquality_pending_fix')
            ->where($qb->expr()->in('page_uid', $qb->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY)))
            ->orderBy('page_uid')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int[] $uids
     */
    public function getPendingByUids(array $uids): array
    {
        $uids = array_values(array_filter(array_map('intval', $uids)));
        if (empty($uids)) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_pending_fix');
        return $qb->select('*')
            ->from('tx_t3contentquality_pending_fix')
            ->where($qb->expr()->in('uid', $qb->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Writes the given pending rows through the DataHandler (permissions, history,
     * workspaces and cache clearing apply) and deletes each row that was written.
     * Rows the DataHandler rejects stay pending.
     *
     * @return array<string, array{pageUid:int, languageUid:int}> affected page/language pairs
     */
    public function applyRows(array $rows): array
    {
        $affected = [];
        $appliedUids = [];

        foreach ($rows as $row) {
            $fixType = (string)$row['fix_type'];
            $pageUid = (int)$row['page_uid'];
            $languageUid = (int)$row['language_uid'];

            if (in_array($fixType, self::PAGE_FIELD_FIX_TYPES, true)) {
                $targetUid = $this->resolvePageRecordUid($pageUid, $languageUid);
                $dataMap = $targetUid !== null ? ['pages' => [$targetUid => [$fixType => (string)$row['new_value']]]] : [];
            } elseif ($fixType === 'alt_text') {
                $dataMap = ['sys_file_reference' => [(int)$row['target_ref'] => ['alternative' => (string)$row['new_value']]]];
            } else {
                continue;
            }

            if ($dataMap === []) {
                $this->logger?->warning('Pending fix skipped: no page record for language', ['pageUid' => $pageUid, 'languageUid' => $languageUid]);
                continue;
            }

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap, []);
            $dataHandler->process_datamap();
            if ($dataHandler->errorLog !== []) {
                $this->logger?->warning('Pending fix rejected by DataHandler', ['uid' => (int)$row['uid'], 'errors' => $dataHandler->errorLog]);
                continue;
            }

            $affected[$pageUid . ':' . $languageUid] = ['pageUid' => $pageUid, 'languageUid' => $languageUid];
            $appliedUids[] = (int)$row['uid'];
        }

        $this->deleteByUids($appliedUids);

        return $affected;
    }

    public function discardRows(array $rows): void
    {
        $this->deleteByUids(array_map(static fn(array $row): int => (int)$row['uid'], $rows));
    }

    /**
     * The page record holding title/description for a language: the page itself
     * for the default language, otherwise its translation (null if none exists).
     */
    private function resolvePageRecordUid(int $pageUid, int $languageUid): ?int
    {
        if ($languageUid <= 0) {
            return $pageUid;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $uid = $qb->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('l10n_parent', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $uid !== false ? (int)$uid : null;
    }

    /**
     * @param int[] $uids
     */
    private function deleteByUids(array $uids): void
    {
        $uids = array_values(array_filter(array_map('intval', $uids)));
        if (empty($uids)) {
            return;
        }
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_t3contentquality_pending_fix');
        $qb->delete('tx_t3contentquality_pending_fix')
            ->where($qb->expr()->in('uid', $qb->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeStatement();
    }
}

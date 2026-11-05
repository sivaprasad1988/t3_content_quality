<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileType;

/**
 * Finds images whose default-language metadata has an empty alternative text
 * and that the AI vision request can handle (mime type, file size).
 * Used by the bulk command t3contentquality:filemetadata:generate.
 */
class FileMetadataCandidateFinder
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param int $afterUid only files with a uid greater than this (paging cursor)
     * @param int $storageUid 0 = all storages
     * @param string $folder folder identifier prefix, e.g. "/user_upload/" ('' = all folders)
     * @return int[] sys_file uids, ascending
     */
    public function findFileUids(int $afterUid, int $batchSize, int $storageUid = 0, string $folder = ''): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->getRestrictions()->removeAll();

        $constraints = [
            $queryBuilder->expr()->gt('f.uid', $queryBuilder->createNamedParameter($afterUid, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('f.type', $queryBuilder->createNamedParameter(FileType::IMAGE->value, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('f.missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->in('f.mime_type', $queryBuilder->createNamedParameter(FileMetadataGenerator::SUPPORTED_MIME_TYPES, Connection::PARAM_STR_ARRAY)),
            $queryBuilder->expr()->lte('f.size', $queryBuilder->createNamedParameter(FileMetadataGenerator::MAX_IMAGE_BYTES, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('m.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->or(
                $queryBuilder->expr()->isNull('m.alternative'),
                $queryBuilder->expr()->eq('m.alternative', $queryBuilder->createNamedParameter(''))
            ),
        ];
        if ($storageUid > 0) {
            $constraints[] = $queryBuilder->expr()->eq('f.storage', $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT));
        }
        if ($folder !== '') {
            $folder = '/' . trim($folder, '/') . '/';
            $constraints[] = $queryBuilder->expr()->like(
                'f.identifier',
                $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($folder) . '%')
            );
        }

        $rows = $queryBuilder
            ->select('f.uid')
            ->from('sys_file', 'f')
            ->join('f', 'sys_file_metadata', 'm', $queryBuilder->expr()->eq('m.file', $queryBuilder->quoteIdentifier('f.uid')))
            // only files of existing storages (files of deleted storages cannot be loaded)
            ->join('f', 'sys_file_storage', 's', (string)$queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('s.uid', $queryBuilder->quoteIdentifier('f.storage')),
                $queryBuilder->expr()->eq('s.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            ))
            ->where(...$constraints)
            ->orderBy('f.uid')
            ->setMaxResults($batchSize)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $rows);
    }
}

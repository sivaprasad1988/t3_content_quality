<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class PageContentExtractor
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Image sys_file_reference rows on a page's content elements that have no ALT text yet.
     */
    public function getImagesMissingAlt(int $pageUid, int $languageUid, int $limit = 10): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');

        return $qb
            ->select('r.uid', 'r.title', 'r.description AS caption', 'f.name AS filename')
            ->from('sys_file_reference', 'r')
            ->join('r', 'sys_file', 'f', 'f.uid = r.uid_local')
            ->join('r', 'tt_content', 'c', 'c.uid = r.uid_foreign')
            ->where(
                $qb->expr()->eq('r.tablenames', $qb->createNamedParameter('tt_content')),
                $qb->expr()->eq('r.fieldname', $qb->createNamedParameter('image')),
                $qb->expr()->eq('c.pid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('c.sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('r.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('r.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->or(
                    $qb->expr()->eq('r.alternative', $qb->createNamedParameter('')),
                    $qb->expr()->isNull('r.alternative')
                )
            )
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function extractFromPage(int $pageUid, int $languageUid = 0): array
    {
        $data = [
            'page_uid' => $pageUid,
            'page_title' => '',
            'seo_title' => '',
            'meta_description' => '',
            'abstract' => '',
            'author' => '',
            'doktype' => 1,
            'slug' => '',
            'crdate' => 0,
            'tstamp' => 0,
            'schema_type_override' => '',
            'headings' => [],
            'images' => [],
            'links' => [],
            'text_content' => '',
            'ctypes' => [],
        ];

        $pageRecord = $this->getPageRecord($pageUid, $languageUid);
        if ($pageRecord) {
            $data['page_title'] = $pageRecord['title'] ?? '';
            $data['seo_title'] = $pageRecord['seo_title'] ?? '';
            $data['meta_description'] = $pageRecord['description'] ?? '';
            $data['abstract'] = $pageRecord['abstract'] ?? '';
            $data['author'] = $pageRecord['author'] ?? '';
            $data['doktype'] = (int)($pageRecord['doktype'] ?? 1);
            $data['slug'] = $pageRecord['slug'] ?? '';
            $data['crdate'] = (int)($pageRecord['crdate'] ?? 0);
            $data['tstamp'] = (int)($pageRecord['tstamp'] ?? 0);
            $data['schema_type_override'] = $pageRecord['tx_t3contentquality_schema_type'] ?? '';
        }

        if (!empty($data['page_title'])) {
            $data['headings'][] = ['level' => 1, 'text' => $data['page_title']];
        }

        $contentElements = $this->getContentElements($pageUid, $languageUid);
        foreach ($contentElements as $element) {
            $this->parseContentElement($element, $data);
        }

        return $data;
    }

    private function getPageRecord(int $pageUid, int $languageUid): ?array
    {
        $coreFields = ['uid', 'title', 'seo_title', 'description', 'abstract', 'author', 'doktype', 'slug', 'crdate', 'tstamp'];

        if ($languageUid > 0) {
            $qb = $this->connectionPool->getQueryBuilderForTable('pages');
            $record = $qb->select(...$coreFields)
                ->from('pages')
                ->where(
                    $qb->expr()->eq('l10n_parent', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                    $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if ($record) {
                $record['tx_t3contentquality_schema_type'] = $this->fetchSchemaTypeOverride((int)$record['uid']);
                return $record;
            }
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $record = $qb->select(...$coreFields)
            ->from('pages')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        if ($record) {
            $record['tx_t3contentquality_schema_type'] = $this->fetchSchemaTypeOverride((int)$record['uid']);
        }

        return $record ?: null;
    }

    private function fetchSchemaTypeOverride(int $pageUid): string
    {
        try {
            $qb = $this->connectionPool->getQueryBuilderForTable('pages');
            $row = $qb->select('tx_t3contentquality_schema_type')
                ->from('pages')
                ->where($qb->expr()->eq('uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchAssociative();
            return (string)($row['tx_t3contentquality_schema_type'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function getContentElements(int $pageUid, int $languageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        return $qb
            ->select('uid', 'CType', 'header', 'header_layout', 'bodytext', 'image')
            ->from('tt_content')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function parseContentElement(array $element, array &$data): void
    {
        if (!empty($element['CType']) && !in_array($element['CType'], $data['ctypes'], true)) {
            $data['ctypes'][] = $element['CType'];
        }

        if (!empty($element['header'])) {
            $level = (int)($element['header_layout'] ?? 0);
            if ($level === 100) {
                // header_layout=100 = hidden, no heading rendered
            } else {
                if ($level === 0) {
                    $level = 2; // header_layout=0 = Default, renders as H2
                }
                $data['headings'][] = ['level' => $level, 'text' => $element['header']];
            }
        }

        if (!empty($element['bodytext'])) {
            $bodytext = $element['bodytext'];
            $data['text_content'] .= ' ' . strip_tags($bodytext);

            preg_match_all('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $bodytext, $linkMatches);
            foreach ($linkMatches[2] as $idx => $linkText) {
                $data['links'][] = [
                    'text' => strip_tags($linkText),
                    'href' => $linkMatches[1][$idx] ?? '',
                ];
            }

            preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', $bodytext, $headingMatches);
            foreach ($headingMatches[1] as $idx => $level) {
                $data['headings'][] = [
                    'level' => (int)$level,
                    'text' => strip_tags($headingMatches[2][$idx]),
                ];
            }
        }

        if (!empty($element['image']) && (int)$element['image'] > 0) {
            foreach ($this->getFileReferences((int)$element['uid'], 'tt_content', 'image') as $image) {
                $data['images'][] = $image;
            }
        }
    }

    private function getFileReferences(int $foreignUid, string $tableName, string $fieldName): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $references = $qb
            ->select('ref.uid', 'ref.alternative', 'ref.title', 'ref.uid_local', 'meta.alternative AS meta_alternative')
            ->from('sys_file_reference', 'ref')
            ->leftJoin(
                'ref',
                'sys_file_metadata',
                'meta',
                (string)$qb->expr()->and(
                    $qb->expr()->eq('meta.file', 'ref.uid_local'),
                    $qb->expr()->eq('meta.sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT))
                )
            )
            ->where(
                $qb->expr()->eq('ref.uid_foreign', $qb->createNamedParameter($foreignUid, Connection::PARAM_INT)),
                $qb->expr()->eq('ref.tablenames', $qb->createNamedParameter($tableName)),
                $qb->expr()->eq('ref.fieldname', $qb->createNamedParameter($fieldName)),
                $qb->expr()->eq('ref.hidden', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->eq('ref.deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($references as &$ref) {
            if (trim((string)($ref['alternative'] ?? '')) === '') {
                $ref['alternative'] = $ref['meta_alternative'] ?? '';
            }
            unset($ref['meta_alternative']);
        }

        return $references;
    }
}

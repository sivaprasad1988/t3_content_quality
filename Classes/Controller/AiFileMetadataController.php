<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Analyzer\AiImageMetadataAnalyzer;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class AiFileMetadataController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // IPTC dataset codes → sys_file_metadata fields
    private const IPTC_MAP = [
        '2#005' => 'title',        // ObjectName
        '2#105' => 'title',        // Headline (fallback for title)
        '2#120' => 'description',  // Caption-Abstract
        '2#080' => 'creator',      // ByLine (author)
        '2#116' => 'copyright',    // CopyrightNotice
        '2#025' => 'keywords',     // Keywords (joined)
    ];

    // EXIF keys → sys_file_metadata fields
    private const EXIF_MAP = [
        'Artist'           => 'creator',
        'Copyright'        => 'copyright',
        'ImageDescription' => 'description',
        'Software'         => 'creator_tool',
        'DocumentName'     => 'title',
    ];

    public function __construct(
        private readonly AiImageMetadataAnalyzer $analyzer,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly AiSettingsFactory $aiSettingsFactory,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $referer = (string)($request->getServerParams()['HTTP_REFERER'] ?? '');
        $fallback = '/typo3/module/file/FilelistList';

        $fileUid = (int)($request->getQueryParams()['fileUid'] ?? 0);
        if ($fileUid <= 0) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (ResourceDoesNotExistException) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        if (!$file->isImage() || !$file->isIndexed() || !$file->checkActionPermission('editMeta')) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        $meta = $file->getMetaData()->get();
        $metaUid = (int)($meta['uid'] ?? 0);
        if ($metaUid <= 0) {
            return new RedirectResponse($referer ?: $fallback, 303);
        }

        // Fields like download_name, caption, creator, copyright, keywords, creator_tool
        // only exist when EXT:filemetadata is installed — drop unknown columns
        $connection = $this->connectionPool->getConnectionForTable('sys_file_metadata');
        $existingColumns = array_change_key_case(
            $connection->createSchemaManager()->listTableColumns('sys_file_metadata')
        );

        $localPath = $file->getForLocalProcessing(false);

        // Step 1: fill from embedded file metadata — free, no tokens
        $updates = $this->extractFromFileMetadata($localPath, $meta);

        // download_name from filename (strip extension) — no AI needed
        if (empty(trim((string)($meta['download_name'] ?? '')))) {
            $updates['download_name'] = pathinfo($file->getName(), PATHINFO_FILENAME);
        }

        // Step 2: AI vision for all fields still missing after EXIF/IPTC
        $aiFields = ['alternative', 'title', 'description', 'caption'];
        $missingForAi = array_filter($aiFields, function (string $field) use ($updates, $meta, $existingColumns): bool {
            if (!isset($existingColumns[$field])) {
                return false;
            }
            $value = $updates[$field] ?? $meta[$field] ?? '';
            return trim((string)$value) === '';
        });

        if (!empty($missingForAi)) {
            $aiResult = $this->runAiVision($file, $localPath, array_values($missingForAi));
            foreach ($missingForAi as $field) {
                if (!empty($aiResult[$field])) {
                    $updates[$field] = $aiResult[$field];
                }
            }
        }

        $updates = array_filter(
            $updates,
            static fn(string $field): bool => isset($existingColumns[strtolower($field)]),
            ARRAY_FILTER_USE_KEY
        );

        if (!empty($updates)) {
            $connection->update(
                'sys_file_metadata',
                $updates,
                ['uid' => $metaUid]
            );
            $this->logger?->info('File metadata updated', [
                'fileUid' => $fileUid,
                'fields'  => array_keys($updates),
            ]);
        }

        // Redirect to metadata edit form so user can review/adjust generated values
        $editUrl = $this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit[sys_file_metadata][' . $metaUid . ']' => 'edit',
            'returnUrl' => $referer ?: $fallback,
        ]);

        return new RedirectResponse((string)$editUrl, 303);
    }

    private function extractFromFileMetadata(string $localPath, array $existingMeta): array
    {
        if (!file_exists($localPath)) {
            return [];
        }

        $updates = [];

        // --- EXIF ---
        $exif = @exif_read_data($localPath);
        if (is_array($exif)) {
            foreach (self::EXIF_MAP as $exifKey => $metaField) {
                if (!empty($exif[$exifKey]) && empty(trim((string)($existingMeta[$metaField] ?? ''))) && !isset($updates[$metaField])) {
                    $updates[$metaField] = (string)$exif[$exifKey];
                }
            }
        }

        // --- IPTC ---
        @getimagesize($localPath, $info);
        if (isset($info['APP13'])) {
            $iptc = @iptcparse($info['APP13']);
            if (is_array($iptc)) {
                $titleSet = false;
                foreach (self::IPTC_MAP as $code => $metaField) {
                    if (empty($iptc[$code])) {
                        continue;
                    }

                    // title: only use first match (2#005 wins over 2#105)
                    if ($metaField === 'title') {
                        if ($titleSet) {
                            continue;
                        }
                        $titleSet = true;
                    }

                    if (empty(trim((string)($existingMeta[$metaField] ?? ''))) && !isset($updates[$metaField])) {
                        $value = $metaField === 'keywords'
                            ? implode(', ', $iptc[$code])
                            : (string)($iptc[$code][0]);
                        $updates[$metaField] = $value;
                    }
                }
            }
        }

        return $updates;
    }

    private function runAiVision(File $file, string $localPath, array $fields = ['alternative']): array
    {
        $aiSettings = $this->aiSettingsFactory->create();
        if (!$aiSettings->isUsable()) {
            return [];
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            return [];
        }

        if ($file->getSize() > self::MAX_IMAGE_BYTES) {
            $this->logger?->info('Image too large for AI vision', ['file' => $file->getName()]);
            return [];
        }

        $content = @file_get_contents($localPath);
        if ($content === false) {
            return [];
        }

        return $this->analyzer->generateMetadata(
            base64_encode($content),
            $mimeType,
            $fields,
            $aiSettings
        );
    }
}

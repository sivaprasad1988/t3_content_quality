<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use Woit\T3ContentQuality\Analyzer\AiImageMetadataAnalyzer;

/**
 * Fills empty sys_file_metadata fields of an image:
 * 1. from embedded EXIF/IPTC data (free, no tokens)
 * 2. download_name from the file name
 * 3. remaining alternative/title/description/caption via AI vision
 *
 * Existing values are never overwritten. Used by the file list button
 * (AiFileMetadataController) and the CLI/scheduler command.
 */
class FileMetadataGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    public const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const AI_FIELDS = ['alternative', 'title', 'description', 'caption'];

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

    /** @var array<string, mixed>|null lower-cased column names of sys_file_metadata */
    private ?array $existingColumns = null;

    public function __construct(
        private readonly AiImageMetadataAnalyzer $analyzer,
        private readonly ConnectionPool $connectionPool,
        private readonly AiSettingsFactory $aiSettingsFactory,
    ) {}

    /**
     * Generates missing metadata for the default-language metadata record of $file
     * and writes it to sys_file_metadata.
     *
     * @return array<string, string> the fields that were written (empty = nothing to do or nothing generated)
     */
    public function generate(File $file): array
    {
        $meta = $file->getMetaData()->get();
        $metaUid = (int)($meta['uid'] ?? 0);
        if ($metaUid <= 0 || !$file->isImage()) {
            return [];
        }

        // Fields like download_name, caption, creator, copyright, keywords, creator_tool
        // only exist when EXT:filemetadata is installed — drop unknown columns
        $existingColumns = $this->getExistingColumns();

        $localPath = $file->getForLocalProcessing(false);

        // Step 1: fill from embedded file metadata — free, no tokens
        $updates = $this->extractFromFileMetadata($localPath, $meta);

        // download_name from filename (strip extension) — no AI needed
        if (empty(trim((string)($meta['download_name'] ?? '')))) {
            $updates['download_name'] = pathinfo($file->getName(), PATHINFO_FILENAME);
        }

        // Step 2: AI vision for all fields still missing after EXIF/IPTC
        $missingForAi = array_filter(self::AI_FIELDS, static function (string $field) use ($updates, $meta, $existingColumns): bool {
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
            $this->connectionPool->getConnectionForTable('sys_file_metadata')->update(
                'sys_file_metadata',
                $updates,
                ['uid' => $metaUid]
            );
            $this->logger?->info('File metadata updated', [
                'fileUid' => $file->getUid(),
                'fields'  => array_keys($updates),
            ]);
        }

        return $updates;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExistingColumns(): array
    {
        if ($this->existingColumns === null) {
            $this->existingColumns = array_change_key_case(
                $this->connectionPool->getConnectionForTable('sys_file_metadata')
                    ->createSchemaManager()
                    ->listTableColumns('sys_file_metadata')
            );
        }
        return $this->existingColumns;
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

    private function runAiVision(File $file, string $localPath, array $fields): array
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

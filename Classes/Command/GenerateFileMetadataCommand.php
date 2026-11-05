<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Woit\T3ContentQuality\Service\FileMetadataCandidateFinder;
use Woit\T3ContentQuality\Service\FileMetadataGenerator;

/**
 * Bulk version of the file list "AI metadata" button.
 *
 * Picks images whose default-language metadata has an empty alternative text
 * and fills all empty metadata fields (EXIF/IPTC first, then AI vision).
 * Existing values are never overwritten.
 *
 * CLI:       vendor/bin/typo3 t3contentquality:filemetadata:generate --limit=20
 * Scheduler: task "Execute console commands", command "t3contentquality:filemetadata:generate"
 */
class GenerateFileMetadataCommand extends Command
{
    private const DEFAULT_LIMIT = 20;
    public const BATCH_SIZE = 200;

    public function __construct(
        private readonly FileMetadataGenerator $fileMetadataGenerator,
        private readonly AiSettingsFactory $aiSettingsFactory,
        private readonly ResourceFactory $resourceFactory,
        private readonly FileMetadataCandidateFinder $candidateFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Fills empty metadata (alternative, title, description, caption, download_name, EXIF/IPTC fields) '
                . 'of images whose alternative text is empty. Existing values are never overwritten. '
                . 'Each processed image costs one AI vision request, so keep --limit small for scheduler runs.'
            )
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of images per run', (string)self::DEFAULT_LIMIT)
            ->addOption('storage', 's', InputOption::VALUE_REQUIRED, 'Only images of this storage uid (0 = all storages)', '0')
            ->addOption('folder', 'f', InputOption::VALUE_REQUIRED, 'Only images below this folder identifier, e.g. "/user_upload/" (requires --storage)', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list the images that would be processed (no AI requests, no DB writes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int)$input->getOption('limit');
        $storageUid = (int)$input->getOption('storage');
        $folder = trim((string)$input->getOption('folder'));
        $dryRun = (bool)$input->getOption('dry-run');

        if ($limit <= 0) {
            $io->error('--limit must be greater than 0.');
            return Command::INVALID;
        }
        if ($folder !== '' && $storageUid <= 0) {
            $io->error('--folder requires --storage.');
            return Command::INVALID;
        }

        if (!$dryRun && !$this->aiSettingsFactory->create()->isUsable()) {
            $io->error('AI is not usable: check aiProvider, API key/Ollama URL and enableAiAnalysis in the extension configuration.');
            return Command::FAILURE;
        }

        $processed = 0;
        $updated = 0;
        $empty = 0;
        $skipped = 0;
        $errors = 0;
        $lastUid = 0;
        // Page through candidates by uid: files that are missing on disk or in an offline
        // storage are skipped and do not count against --limit, so they cannot block the queue.
        while ($processed < $limit) {
            $fileUids = $this->candidateFinder->findFileUids($lastUid, self::BATCH_SIZE, $storageUid, $folder);
            if ($fileUids === []) {
                break;
            }
            foreach ($fileUids as $fileUid) {
                $lastUid = $fileUid;
                if ($processed >= $limit) {
                    break;
                }
                try {
                    $file = $this->resourceFactory->getFileObject($fileUid);
                    if (!$file->getStorage()->isOnline() || !$file->exists()) {
                        $io->writeln(sprintf('  <comment>skip</comment> %d %s (storage offline or file missing)', $fileUid, $file->getCombinedIdentifier()), OutputInterface::VERBOSITY_VERBOSE);
                        $skipped++;
                        continue;
                    }
                    $processed++;
                    if ($dryRun) {
                        $io->writeln(sprintf('  %d %s', $fileUid, $file->getCombinedIdentifier()));
                        continue;
                    }

                    $fields = $this->fileMetadataGenerator->generate($file);
                    if ($fields === []) {
                        $io->writeln(sprintf('  <comment>none</comment> %d %s', $fileUid, $file->getCombinedIdentifier()));
                        $empty++;
                    } else {
                        $io->writeln(sprintf('  <info>ok</info>   %d %s [%s]', $fileUid, $file->getCombinedIdentifier(), implode(', ', array_keys($fields))));
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $io->writeln(sprintf('  <error>fail</error> %d: %s', $fileUid, $e->getMessage()));
                    $errors++;
                }
            }
        }

        if ($dryRun) {
            $io->writeln(sprintf('Dry run: %d image(s) would be processed, %d skipped (missing/offline, -v lists them).', $processed, $skipped));
            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            'Updated: %d, nothing generated: %d, skipped (missing/offline): %d, errors: %d',
            $updated,
            $empty,
            $skipped,
            $errors
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

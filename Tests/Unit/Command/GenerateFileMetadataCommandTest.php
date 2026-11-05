<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use Woit\T3ContentQuality\Command\GenerateFileMetadataCommand;
use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Woit\T3ContentQuality\Service\FileMetadataCandidateFinder;
use Woit\T3ContentQuality\Service\FileMetadataGenerator;

final class GenerateFileMetadataCommandTest extends TestCase
{
    private FileMetadataGenerator&MockObject $generator;
    private AiSettingsFactory&MockObject $aiSettingsFactory;
    private ResourceFactory&MockObject $resourceFactory;
    private FileMetadataCandidateFinder&MockObject $finder;

    /** @var array<int, File> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->generator = $this->createMock(FileMetadataGenerator::class);
        $this->aiSettingsFactory = $this->createMock(AiSettingsFactory::class);
        $this->resourceFactory = $this->createMock(ResourceFactory::class);
        $this->finder = $this->createMock(FileMetadataCandidateFinder::class);

        $this->resourceFactory->method('getFileObject')->willReturnCallback(
            fn(int $uid): File => $this->files[$uid] ?? throw new \RuntimeException('Unknown file ' . $uid)
        );
    }

    #[Test]
    public function limitMustBeGreaterThanZero(): void
    {
        $this->finder->expects(self::never())->method('findFileUids');

        $tester = $this->executeCommand(['--limit' => '0']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('--limit must be greater than 0', $tester->getDisplay());
    }

    #[Test]
    public function folderRequiresStorage(): void
    {
        $this->finder->expects(self::never())->method('findFileUids');

        $tester = $this->executeCommand(['--folder' => '/user_upload/']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('--folder requires --storage', $tester->getDisplay());
    }

    #[Test]
    public function failsWhenAiIsNotUsable(): void
    {
        $this->aiSettingsFactory->method('create')->willReturn(new AiSettings('anthropic', '', 'model', 1000));
        $this->finder->expects(self::never())->method('findFileUids');
        $this->generator->expects(self::never())->method('generate');

        $tester = $this->executeCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('AI is not usable', $tester->getDisplay());
    }

    #[Test]
    public function succeedsWhenNoCandidatesExist(): void
    {
        $this->useUsableAi();
        $this->finder->method('findFileUids')->willReturn([]);
        $this->generator->expects(self::never())->method('generate');

        $tester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Updated: 0', $tester->getDisplay());
    }

    #[Test]
    public function dryRunListsImagesWithoutAiAndWithoutWriting(): void
    {
        $this->aiSettingsFactory->expects(self::never())->method('create');
        $this->generator->expects(self::never())->method('generate');
        $this->addFile(5, '1:/user_upload/a.jpg');
        $this->addFile(6, '1:/user_upload/b.png');
        $this->finder->method('findFileUids')->willReturnOnConsecutiveCalls([5, 6], []);

        $tester = $this->executeCommand(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('1:/user_upload/a.jpg', $display);
        self::assertStringContainsString('1:/user_upload/b.png', $display);
        self::assertStringContainsString('Dry run: 2 image(s) would be processed, 0 skipped', $display);
    }

    #[Test]
    public function storageAndFolderOptionsArePassedToTheFinder(): void
    {
        $this->useUsableAi();
        $this->finder->expects(self::once())
            ->method('findFileUids')
            ->with(0, GenerateFileMetadataCommand::BATCH_SIZE, 1, '/user_upload/')
            ->willReturn([]);

        $tester = $this->executeCommand(['--storage' => '1', '--folder' => '/user_upload/']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    #[Test]
    public function missingFilesAreSkippedAndDoNotCountAgainstTheLimit(): void
    {
        $this->useUsableAi();
        $this->addFile(1, '1:/missing.jpg', exists: false);
        $this->addFile(2, '1:/two.jpg');
        $this->addFile(3, '1:/three.jpg');
        $this->addFile(4, '1:/four.jpg');
        $this->finder->expects(self::once())->method('findFileUids')->willReturn([1, 2, 3, 4]);
        $this->generator->expects(self::exactly(2))
            ->method('generate')
            ->willReturnCallback(function (File $file): array {
                self::assertContains($file->getUid(), [2, 3]);
                return ['alternative' => 'Alt'];
            });

        $tester = $this->executeCommand(['--limit' => '2']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'Updated: 2, nothing generated: 0, skipped (missing/offline): 1, errors: 0',
            $tester->getDisplay()
        );
    }

    #[Test]
    public function offlineStorageIsSkipped(): void
    {
        $this->useUsableAi();
        $this->addFile(1, '2:/offline.jpg', storageOnline: false);
        $this->finder->method('findFileUids')->willReturnOnConsecutiveCalls([1], []);
        $this->generator->expects(self::never())->method('generate');

        $tester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('skipped (missing/offline): 1', $tester->getDisplay());
    }

    #[Test]
    public function candidatesArePagedWithTheLastUidAsCursor(): void
    {
        $this->useUsableAi();
        $this->addFile(10, '1:/a.jpg');
        $this->addFile(20, '1:/b.jpg');
        $this->addFile(30, '1:/c.jpg');
        $calls = [];
        $this->finder->method('findFileUids')->willReturnCallback(
            function (int $afterUid) use (&$calls): array {
                $calls[] = $afterUid;
                return match ($afterUid) {
                    0 => [10, 20],
                    20 => [30],
                    default => [],
                };
            }
        );
        $this->generator->expects(self::exactly(3))->method('generate')->willReturn(['alternative' => 'Alt']);

        $tester = $this->executeCommand(['--limit' => '10']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([0, 20, 30], $calls);
        self::assertStringContainsString('Updated: 3', $tester->getDisplay());
    }

    #[Test]
    public function imageWithoutGeneratedValuesIsCountedAsNothingGenerated(): void
    {
        $this->useUsableAi();
        $this->addFile(1, '1:/a.jpg');
        $this->finder->method('findFileUids')->willReturnOnConsecutiveCalls([1], []);
        $this->generator->method('generate')->willReturn([]);

        $tester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Updated: 0, nothing generated: 1', $tester->getDisplay());
    }

    #[Test]
    public function exceptionIsReportedAndRunContinuesWithFailureExitCode(): void
    {
        $this->useUsableAi();
        $this->addFile(1, '1:/broken.jpg');
        $this->addFile(2, '1:/ok.jpg');
        $this->finder->method('findFileUids')->willReturnOnConsecutiveCalls([1, 2], []);
        $this->generator->method('generate')->willReturnCallback(
            static fn(File $file): array => $file->getUid() === 1
                ? throw new \RuntimeException('boom')
                : ['alternative' => 'Alt']
        );

        $tester = $this->executeCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('fail 1: boom', $display);
        self::assertStringContainsString('Updated: 1, nothing generated: 0, skipped (missing/offline): 0, errors: 1', $display);
    }

    private function executeCommand(array $input = []): CommandTester
    {
        $tester = new CommandTester(new GenerateFileMetadataCommand(
            $this->generator,
            $this->aiSettingsFactory,
            $this->resourceFactory,
            $this->finder,
        ));
        $tester->execute($input);
        return $tester;
    }

    private function useUsableAi(): void
    {
        $this->aiSettingsFactory->method('create')->willReturn(new AiSettings('ollama', '', 'gemma3:4b', 1000));
    }

    private function addFile(int $uid, string $combinedIdentifier, bool $exists = true, bool $storageOnline = true): void
    {
        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('isOnline')->willReturn($storageOnline);

        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn($uid);
        $file->method('getStorage')->willReturn($storage);
        $file->method('exists')->willReturn($exists);
        $file->method('getCombinedIdentifier')->willReturn($combinedIdentifier);

        $this->files[$uid] = $file;
    }
}

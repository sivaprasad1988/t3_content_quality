<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Service;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\MetaDataAspect;
use Woit\T3ContentQuality\Analyzer\AiImageMetadataAnalyzer;
use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Woit\T3ContentQuality\Service\FileMetadataGenerator;

final class FileMetadataGeneratorTest extends TestCase
{
    private const ALL_COLUMNS = ['uid', 'alternative', 'title', 'description', 'caption', 'download_name'];

    private AiImageMetadataAnalyzer&MockObject $analyzer;
    private Connection&MockObject $connection;
    private string $localFile;

    protected function setUp(): void
    {
        $this->analyzer = $this->createMock(AiImageMetadataAnalyzer::class);
        $this->connection = $this->createMock(Connection::class);
        // Plain bytes without EXIF/IPTC: only the AI and the file name can provide values
        $this->localFile = tempnam(sys_get_temp_dir(), 't3cq');
        file_put_contents($this->localFile, 'not a real image');
    }

    protected function tearDown(): void
    {
        @unlink($this->localFile);
    }

    #[Test]
    public function fileWithoutMetadataRecordIsIgnored(): void
    {
        $this->analyzer->expects(self::never())->method('generateMetadata');
        $this->connection->expects(self::never())->method('update');

        self::assertSame([], $this->generator()->generate($this->file([])));
    }

    #[Test]
    public function nonImageIsIgnored(): void
    {
        $this->analyzer->expects(self::never())->method('generateMetadata');
        $this->connection->expects(self::never())->method('update');

        self::assertSame([], $this->generator()->generate($this->file(['uid' => 3], isImage: false)));
    }

    #[Test]
    public function onlyEmptyFieldsAreRequestedAndExistingValuesAreKept(): void
    {
        $this->analyzer->expects(self::once())
            ->method('generateMetadata')
            ->with(
                base64_encode('not a real image'),
                'image/jpeg',
                ['alternative', 'description', 'caption'],
                self::isInstanceOf(AiSettings::class)
            )
            ->willReturn([
                'alternative' => 'Alt text',
                'title' => 'AI title must not overwrite',
                'description' => 'Description',
                'caption' => 'Caption',
            ]);

        $expected = [
            'download_name' => 'my-photo',
            'alternative' => 'Alt text',
            'description' => 'Description',
            'caption' => 'Caption',
        ];
        $this->connection->expects(self::once())
            ->method('update')
            ->with('sys_file_metadata', $expected, ['uid' => 3]);

        $result = $this->generator()->generate($this->file([
            'uid' => 3,
            'alternative' => '',
            'title' => 'Existing title',
            'caption' => '  ',
            'download_name' => '',
        ]));

        self::assertSame($expected, $result);
    }

    #[Test]
    public function fieldsWithoutDatabaseColumnAreNeitherRequestedNorWritten(): void
    {
        // Without EXT:filemetadata there is no caption and no download_name column
        $columns = ['uid', 'alternative', 'title', 'description'];

        $this->analyzer->expects(self::once())
            ->method('generateMetadata')
            ->with(self::anything(), self::anything(), ['alternative', 'title', 'description'], self::anything())
            ->willReturn(['alternative' => 'Alt', 'title' => 'Title', 'description' => 'Desc', 'caption' => 'Cap']);

        $expected = ['alternative' => 'Alt', 'title' => 'Title', 'description' => 'Desc'];
        $this->connection->expects(self::once())
            ->method('update')
            ->with('sys_file_metadata', $expected, ['uid' => 3]);

        self::assertSame($expected, $this->generator(columns: $columns)->generate($this->file(['uid' => 3])));
    }

    #[Test]
    public function nothingIsWrittenWhenAllFieldsAreFilled(): void
    {
        $this->analyzer->expects(self::never())->method('generateMetadata');
        $this->connection->expects(self::never())->method('update');

        $result = $this->generator()->generate($this->file([
            'uid' => 3,
            'alternative' => 'Alt',
            'title' => 'Title',
            'description' => 'Desc',
            'caption' => 'Cap',
            'download_name' => 'name',
        ]));

        self::assertSame([], $result);
    }

    #[Test]
    public function aiIsSkippedWhenProviderIsNotUsable(): void
    {
        $this->analyzer->expects(self::never())->method('generateMetadata');
        $this->connection->expects(self::once())
            ->method('update')
            ->with('sys_file_metadata', ['download_name' => 'my-photo'], ['uid' => 3]);

        $generator = $this->generator(settings: new AiSettings('anthropic', '', 'model', 1000));

        self::assertSame(['download_name' => 'my-photo'], $generator->generate($this->file(['uid' => 3])));
    }

    public static function imagesNotSentToAi(): array
    {
        return [
            'unsupported mime type' => ['image/tiff', 1000],
            'too large' => ['image/jpeg', FileMetadataGenerator::MAX_IMAGE_BYTES + 1],
        ];
    }

    #[Test]
    #[DataProvider('imagesNotSentToAi')]
    public function unsupportedOrTooLargeImagesAreNotSentToAi(string $mimeType, int $size): void
    {
        $this->analyzer->expects(self::never())->method('generateMetadata');
        $this->connection->expects(self::once())
            ->method('update')
            ->with('sys_file_metadata', ['download_name' => 'my-photo'], ['uid' => 3]);

        $result = $this->generator()->generate($this->file(['uid' => 3], mimeType: $mimeType, size: $size));

        self::assertSame(['download_name' => 'my-photo'], $result);
    }

    #[Test]
    public function emptyAiResultWritesNothingButDownloadName(): void
    {
        $this->analyzer->method('generateMetadata')->willReturn([]);
        $this->connection->expects(self::once())
            ->method('update')
            ->with('sys_file_metadata', ['download_name' => 'my-photo'], ['uid' => 3]);

        self::assertSame(['download_name' => 'my-photo'], $this->generator()->generate($this->file(['uid' => 3])));
    }

    private function generator(array $columns = self::ALL_COLUMNS, ?AiSettings $settings = null): FileMetadataGenerator
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('listTableColumns')->willReturn(array_fill_keys($columns, true));
        $this->connection->method('createSchemaManager')->willReturn($schemaManager);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($this->connection);

        $aiSettingsFactory = $this->createMock(AiSettingsFactory::class);
        $aiSettingsFactory->method('create')->willReturn($settings ?? new AiSettings('ollama', '', 'gemma3:4b', 1000));

        return new FileMetadataGenerator($this->analyzer, $connectionPool, $aiSettingsFactory);
    }

    private function file(array $meta, bool $isImage = true, string $mimeType = 'image/jpeg', int $size = 1000): File
    {
        $metaDataAspect = $this->createMock(MetaDataAspect::class);
        $metaDataAspect->method('get')->willReturn($meta);

        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(7);
        $file->method('getMetaData')->willReturn($metaDataAspect);
        $file->method('isImage')->willReturn($isImage);
        $file->method('getForLocalProcessing')->willReturn($this->localFile);
        $file->method('getName')->willReturn('my-photo.jpg');
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getSize')->willReturn($size);

        return $file;
    }
}

<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Analyzer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use Woit\T3ContentQuality\Analyzer\AiImageMetadataAnalyzer;
use Woit\T3ContentQuality\Analyzer\AiPageFixer;
use Woit\T3ContentQuality\Analyzer\AiSchemaDetector;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\OllamaEndpoint;

final class AiResponseParsingTest extends TestCase
{
    #[Test]
    public function pageFixerParsesJsonWrappedInCodeFencesAndText(): void
    {
        $parsed = (new AiPageFixer($this->client()))->parseJson("```json\n{\"title\": \"T\", \"description\": \"D\"}\n```\nDone.");

        self::assertSame(['title' => 'T', 'description' => 'D'], $parsed);
    }

    #[Test]
    public function pageFixerRejectsNonJson(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AiPageFixer($this->client()))->parseJson('no json here');
    }

    #[Test]
    public function altTextLinesAreMappedToReferenceUids(): void
    {
        $parsed = (new AiPageFixer($this->client()))->parseAltTextLines("42| Garden\n87: Kitchen\nnoise");

        self::assertSame([42 => 'Garden', 87 => 'Kitchen'], $parsed);
    }

    #[Test]
    public function imageMetadataKeepsOnlyKnownNonEmptyFields(): void
    {
        $parsed = (new AiImageMetadataAnalyzer($this->client()))->parseJsonResponse(
            '{"alternative":"A","title":"","caption":"C","evil":"x"}'
        );

        self::assertSame(['alternative' => 'A', 'caption' => 'C'], $parsed);
    }

    #[Test]
    public function schemaDetectorNeedsType(): void
    {
        $detector = new AiSchemaDetector($this->client());

        self::assertSame(['type' => 'Event', 'fields' => ['name' => 'Fair']], $detector->parseResponse('{"type":"Event","fields":{"name":"Fair"}}'));
        self::assertNull($detector->parseResponse('{"fields":{}}'));
    }

    private function client(): AiProviderClient
    {
        return new AiProviderClient(
            $this->createMock(RequestFactory::class),
            new OllamaEndpoint($this->createMock(ExtensionConfiguration::class))
        );
    }
}

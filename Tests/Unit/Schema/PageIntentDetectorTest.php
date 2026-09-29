<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Woit\T3ContentQuality\Schema\PageIntentDetector;
use Woit\T3ContentQuality\Schema\SchemaTypes;

final class PageIntentDetectorTest extends TestCase
{
    public static function pageDataProvider(): iterable
    {
        yield 'manual override wins' => [
            ['schema_type_override' => SchemaTypes::PRODUCT, 'page_title' => 'FAQ', 'ctypes' => ['tx_news_pi1']],
            SchemaTypes::PRODUCT,
        ];
        yield 'news plugin' => [['page_title' => 'Aktuelles', 'ctypes' => ['tx_news_pi1']], SchemaTypes::NEWS_ARTICLE];
        yield 'faq keyword' => [['page_title' => 'FAQ zur Anmeldung'], SchemaTypes::FAQ_PAGE];
        yield 'german event keyword' => [['page_title' => 'Veranstaltung im Mai'], SchemaTypes::EVENT];
        yield 'opening hours' => [['meta_description' => 'Unsere Öffnungszeiten'], SchemaTypes::LOCAL_BUSINESS];
        yield 'umlaut keyword at word start' => [['page_title' => 'Über uns'], SchemaTypes::ORGANIZATION];
        yield 'fallback' => [['page_title' => 'Kontakt'], SchemaTypes::WEB_PAGE];
        yield 'no text at all' => [[], null];
    }

    #[Test]
    #[DataProvider('pageDataProvider')]
    public function detectsSchemaType(array $pageData, ?string $expected): void
    {
        self::assertSame($expected, (new PageIntentDetector())->detect($pageData));
    }
}

<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Woit\T3ContentQuality\Schema\SchemaTypes;
use Woit\T3ContentQuality\Schema\SchemaValidator;

final class SchemaValidatorTest extends TestCase
{
    #[Test]
    public function missingRequiredPropertyCosts25AndRecommendedCosts5(): void
    {
        $validator = new SchemaValidator();

        // Event: required name + startDate, six recommended properties
        $score = $validator->calculateScore(SchemaTypes::EVENT, ['name' => 'Summer fair']);

        self::assertSame(100 - 25 - 6 * 5, $score);
    }

    #[Test]
    public function emptyStringsCountAsMissing(): void
    {
        $validator = new SchemaValidator();

        self::assertSame(
            $validator->calculateScore(SchemaTypes::WEB_PAGE, []),
            $validator->calculateScore(SchemaTypes::WEB_PAGE, ['name' => '   '])
        );
    }
}

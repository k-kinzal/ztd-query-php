<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Syntax\NumericLiteral as Subject;

#[CoversClass(Subject::class)]
final class NumericLiteralTest extends TestCase
{
    #[DataProvider('providerNumbers')]
    public function testDecodeReturnsIntegersForWholeNumbersAndFloatsOtherwise(string $text, int|float $expected): void
    {
        self::assertSame($expected, (new Subject())->decode($text));
    }

    /**
     * @return list<array{string, int|float}>
     */
    public static function providerNumbers(): array
    {
        return [
            ['42', 42],
            ['-42', -42],
            ['+7', 7],
            ['0', 0],
            ['9.99', 9.99],
            ['-1.5', -1.5],
            ['1e3', 1000.0],
            ['99999999999999999999', 1.0E+20],
        ];
    }
}

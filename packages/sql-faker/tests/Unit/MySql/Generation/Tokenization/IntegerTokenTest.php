<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Generation\Tokenization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Generation\Tokenization\IntegerToken;

#[CoversClass(IntegerToken::class)]
final class IntegerTokenTest extends TestCase
{
    #[DataProvider('providerMagnitudes')]
    public function testOfPreservesIntegerBoundariesBeyondPhpRange(string $digits, string $expected): void
    {
        self::assertSame($expected, IntegerToken::of($digits));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMagnitudes(): iterable
    {
        yield 'leading zeros' => ['00000000002147483647', 'NUM'];
        yield 'signed 64-bit' => ['9223372036854775807', 'LONG_NUM'];
        yield 'unsigned 64-bit' => ['18446744073709551615', 'ULONGLONG_NUM'];
        yield 'overflow' => ['18446744073709551616', 'DECIMAL_NUM'];
    }
}

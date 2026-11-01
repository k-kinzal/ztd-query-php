<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\JsonWidths;

#[CoversClass(JsonWidths::class)]
#[Small]
final class JsonWidthsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function providerWidths(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'fraction' => ['.5', 0];
        yield 'decimal prefix' => ['1.9', 1];
        yield 'width boundary' => ['4294967295', 4294967295];
        yield 'width excess' => ['4294967296', null];
        yield 'signed maximum' => ['9223372036854775807', null];
        yield 'upper half begins' => ['9223372036854775808', 0];
        yield 'upper half one' => ['9223372036854775809', 1];
        yield 'upper half three' => ['9223372036854775811', 3];
        yield 'last low-word boundary' => ['18446744069414584320', 0];
        yield 'unsigned maximum neighbour' => ['18446744073709551614', 4294967294];
        yield 'unsigned maximum' => ['18446744073709551615', 4294967295];
        yield 'padded maximum' => ['00018446744073709551615.9', 4294967295];
        yield 'unsigned overflow' => ['18446744073709551616', null];
        yield 'larger decimal' => ['999999999999999999999', null];
    }

    #[DataProvider('providerWidths')]
    public function testOfKeepsUnsignedBoundariesWithoutSaturating(string $length, ?int $expected): void
    {
        self::assertSame($expected, (new JsonWidths())->of($length));
    }
}

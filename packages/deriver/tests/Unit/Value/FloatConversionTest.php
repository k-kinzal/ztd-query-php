<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\FloatConversion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FloatConversion::class)]
#[Small]
final class FloatConversionTest extends TestCase
{
    public function testStringIsUnknownWithoutACapturedPrecision(): void
    {
        self::assertNull((new FloatConversion())->string(0.25));
    }

    /**
     * @param int $precision Captured precision directive
     * @param float $value Float operand
     * @param string $expected String observed from PHP 8.3 under the same directive
     */
    #[DataProvider('providerStrings')]
    public function testStringMatchesTheTargetConversion(int $precision, float $value, string $expected): void
    {
        self::assertSame($expected, (new FloatConversion($precision))->string($value));
    }

    /**
     * @return array<string, array{int, float, string}>
     */
    public static function providerStrings(): array
    {
        return [
            'default precision' => [14, 1 / 3, '0.33333333333333'],
            'shortest round trip' => [-1, 0.1 + 0.2, '0.30000000000000004'],
            'exponent form' => [14, 1e15, '1.0E+15'],
            'negative zero' => [14, -0.0, '-0'],
            'zero precision' => [0, 2.5, '2'],
            'infinity' => [14, -INF, '-INF'],
            'truncated infinity' => [2, INF, 'IN'],
            'not a number' => [14, NAN, 'NAN'],
            'shortest not a number' => [-1, NAN, 'NAN'],
            'truncated not a number' => [2, NAN, 'NA'],
            'single character not a number' => [0, NAN, 'N'],
        ];
    }

    public function testWithinRestoresTheHostPrecision(): void
    {
        $host = ini_get('precision');
        self::assertSame('0.3', (new FloatConversion(1))->within(static fn (): string => (string) 0.26));
        self::assertSame($host, ini_get('precision'));
    }
}

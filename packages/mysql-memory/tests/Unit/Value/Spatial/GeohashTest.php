<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Geohash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Geohash::class)]
#[Small]
final class GeohashTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float, int, string}> Native encodings and floating-point termination
     */
    public static function providerPoints(): iterable
    {
        yield 'unordered longitude' => [NAN, 0.0, 10, 'xbpbpbpbpb'];
        yield 'unordered latitude' => [0.0, NAN, 10, 'upbpbpbpbp'];
        yield 'origin' => [0.0, 0.0, 10, 's000000000'];
        yield 'ordinary' => [1.0, 2.0, 10, 's02equ04ve'];
        yield 'exact center' => [1.0, 2.0, 100, 's02equ04ven09qv80meqh1e1g'];
        yield 'lower corner' => [-180.0, -90.0, 100, '0000000000000000000003'];
        yield 'upper corner' => [180.0, 90.0, 100, str_repeat('z', 22)];
        yield 'negative coordinates' => [-5.6, 42.6, 20, 'ezs42e44yx9675pdcpwd'];
        yield 'fractional coordinates' => [0.1, 0.2, 100, 's0014k2v44tqkzn4kv6ez9erz'];
        yield 'origin retains requested length' => [0.0, 0.0, 100, 's' . str_repeat('0', 99)];
    }

    #[DataProvider('providerPoints')]
    public function testEncodeMatchesObservedCellsAndTermination(float $longitude, float $latitude, int $length, string $expected): void
    {
        self::assertSame($expected, Geohash::encode($longitude, $latitude, $length));
    }

    /**
     * @return iterable<string, array{string, array{float, float}|null}> Decoded centers and invalid prefixes
     */
    public static function providerHashes(): iterable
    {
        yield 'coarse center' => ['s', [22.0, 22.0]];
        yield 'negative coarse center' => ['0', [-158.0, -68.0]];
        yield 'decimal center' => ['ezs42', [-5.6, 42.6]];
        yield 'precise center' => ['u4pruydqqvj', [10.40744, 57.64911]];
        yield 'uppercase' => ['ZZZZ', [180.0, 90.0]];
        yield 'empty' => ['', null];
        yield 'invalid alphabet' => ['a', null];
        yield 'invalid ending' => [str_repeat('0', 432) . '!', null];
        yield 'ignored ending' => [str_repeat('0', 433) . '!', [-180.0, -90.0]];
    }

    /**
     * @param array{float, float}|null $expected Decoded coordinate pair
     */
    #[DataProvider('providerHashes')]
    public function testDecodeValidatesTheBoundedPrefixAndRoundsInsideTheCell(string $hash, ?array $expected): void
    {
        self::assertSame($expected, Geohash::decode($hash));
    }

    public function testRoundedPreservesTheCellAndUsesEvenHalfwayRounding(): void
    {
        self::assertSame(22.0, Geohash::rounded(0.0, 45.0));
        self::assertSame(-68.0, Geohash::rounded(-90.0, -45.0));
        self::assertSame(0.000000001, Geohash::rounded(0.00000000099999999, 0.00000000100000001));
    }
}

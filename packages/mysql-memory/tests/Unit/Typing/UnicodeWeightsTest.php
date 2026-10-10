<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\UnicodeWeights;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnicodeWeights::class)]
#[Small]
final class UnicodeWeightsTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function providerPrimaryWeights(): array
    {
        return [
            'U+0061' => [0x61, '1C47'],
            'U+0041' => [0x41, '1C47'],
            'U+00E9' => [0xE9, '1CAA'],
            'U+00DF' => [0xDF, '1E711E71'],
            'U+FDFA' => [0xFDFA, '2364239C23C50209230B239C239C23B1'],
            'U+4E00' => [0x4E00, 'FB40CE00'],
            'U+3400' => [0x3400, 'FB80B400'],
            'U+20000' => [0x20000, 'FB848000'],
            'U+FA0E' => [0xFA0E, 'FB41FA0E'],
            'U+9FD5' => [0x9FD5, 'FB419FD5'],
            'U+9FD6' => [0x9FD6, 'FBC19FD6'],
            'U+2CEA1' => [0x2CEA1, 'FB85CEA1'],
            'U+2CEA2' => [0x2CEA2, 'FBC5CEA2'],
            'U+0378' => [0x378, 'FBC08378'],
            'U+10FFFF' => [0x10FFFF, 'FBE1FFFF'],
            'U+17000' => [0x17000, 'FB008000'],
            'U+18AFF' => [0x18AFF, 'FB009AFF'],
            'U+18B00' => [0x18B00, 'FBC38B00'],
            'U+187ED' => [0x187ED, 'FB0097ED'],
            'U+AC00' => [0xAC00, '3BF53C73'],
            'U+D7A3' => [0xD7A3, '3C073C873CEB'],
            'U+D7A4' => [0xD7A4, '3C083C73'],
            'U+D7AF' => [0xD7AF, '3C083C733CDB'],
            'U+D7B0' => [0xD7B0, '3CBA'],
            'U+0000' => [0x0, ''],
            'U+0301' => [0x301, ''],
            'U+FE0F' => [0xFE0F, ''],
            'U+1F600' => [0x1F600, '15FB'],
        ];
    }

    #[DataProvider('providerPrimaryWeights')]
    public function testPrimaryMatchesObservedExpansionsAndImplicitBoundaries(int $point, string $expected): void
    {
        self::assertSame(strtolower($expected), bin2hex((new UnicodeWeights())->primary($point)));
    }
}

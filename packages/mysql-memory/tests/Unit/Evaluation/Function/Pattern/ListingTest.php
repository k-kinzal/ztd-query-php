<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Listing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Transliterator;

#[CoversClass(Listing::class)]
#[Small]
final class ListingTest extends TestCase
{
    public function testRangesReadsTheSetOfAPatternFromIcu(): void
    {
        self::assertSame('\x{30}-\x{39}\x{41}-\x{46}\x{61}-\x{66}\x{FF10}-\x{FF19}\x{FF21}-\x{FF26}\x{FF41}-\x{FF46}', (new Listing())->ranges(static fn (int $code): bool => false, '[:Hex_Digit:]'));
    }

    public function testRangesTestsTheSurrogatesOneByOne(): void
    {
        self::assertSame('\x{CFFF}\x{D800}-\x{E000}', (new Listing())->ranges(static fn (int $code): bool => $code >= 0xD800 && $code <= 0xDFFF, '[\x{CFFF}\x{E000}]'));
    }

    public function testRangesTestsEveryCodePointBelowTheLimitWithoutAPatternIcuReads(): void
    {
        self::assertSame(['\x{41}-\x{43}\x{1FFF}', '\x{41}-\x{43}'], [(new Listing())->ranges(static fn (int $code): bool => in_array($code, [0x41, 0x42, 0x43, 0x1FFF, 0x2000], true), null, 0x2000), (new Listing())->ranges(static fn (int $code): bool => in_array($code, [0x41, 0x42, 0x43], true), '[:Foo:]', 0x1000)]);
    }

    public function testReadAnswersTheRunsATransliteratorLeaves(): void
    {
        $remover = Transliterator::create('[^[a-c][x]] Remove');

        self::assertNotNull($remover);
        self::assertSame([[[0x61, 0x63], [0x78, 0x78]], [], [[0x1000, 0x1FFF]]], [(new Listing())->read(0, $remover), (new Listing())->read(0x1000, Transliterator::create('[\x{0}-\x{10FFFF}] Remove') ?? $remover), (new Listing())->read(0x1000, Transliterator::create('[\x{0}] Remove') ?? $remover)]);
    }

    public function testTestedAnswersTheRunsOfTheCodePointsThatPass(): void
    {
        self::assertSame([[0x1001, 0x1002], [0x1FFF, 0x1FFF]], (new Listing())->tested(0x1000, static fn (int $code): bool => in_array($code, [0x1001, 0x1002, 0x1FFF], true)));
    }

    public function testRunsJoinsConsecutiveCodePoints(): void
    {
        self::assertSame([[1, 3], [5, 5], [7, 8]], (new Listing())->runs([1, 2, 3, 5, 7, 8]));
    }

    public function testTextWritesTheCodePointsOfABlockWithoutTheSurrogates(): void
    {
        $listing = new Listing();

        self::assertSame([0x1000, 0x800, 0x1000], [mb_strlen($listing->text(0), 'UTF-8'), mb_strlen($listing->text(0xD000), 'UTF-8'), mb_strlen($listing->text(0x10F000), 'UTF-8')]);
    }
}

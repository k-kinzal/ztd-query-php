<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(CastProblems::class)]
#[Small]
final class CastProblemsTest extends TestCase
{
    /**
     * @return iterable<string, array{CastKind, ?string, ?int, int}>
     */
    public static function providerPrecisions(): iterable
    {
        yield 'float omitted' => [CastKind::Float, null, null, 53];
        yield 'float boundary' => [CastKind::Float, '53', null, 53];
        yield 'float excess' => [CastKind::Float, '54', 54, 53];
        yield 'signed overflow' => [CastKind::Float, '2147483648', -2147483648, 53];
        yield 'unsigned overflow' => [CastKind::Float, '4294967296', 0, 53];
        yield 'unsigned maximum' => [CastKind::Float, '18446744073709551615', -1, 53];
        yield 'time boundary' => [CastKind::Time, '6', null, 6];
        yield 'time excess' => [CastKind::Time, '7', 7, 6];
        yield 'datetime excess' => [CastKind::DateTime, '7', 7, 6];
        yield 'character length' => [CastKind::Char, '54', null, 6];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerPrecisions')]
    public function testPrecisionPreservesTheLimitAndReportedPrecision(CastKind $kind, ?string $length, ?int $reported, int $maximum): void
    {
        self::assertEquals($reported === null ? null : new TooBigPrecision($reported, 'CAST', $maximum), (new CastProblems())->precision(new CastTarget($kind, $length)));
    }

    public function testReturningAcceptsUnsignedUpperHalfWidthsButRefusesAdjacentOverflow(): void
    {
        $rules = new CastProblems();
        self::assertNull($rules->returning(new CastTarget(CastKind::Binary, '18446744073709551615')));
        self::assertNull($rules->returning(new CastTarget(CastKind::Char, '9223372036854775808')));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize::class, $rules->returning(new CastTarget(CastKind::Binary, '9223372036854775807')));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize::class, $rules->returning(new CastTarget(CastKind::Binary, '18446744073709551616')));
    }

    public function testReturningKeepsJsonTargetsDistinctFromOrdinaryCasts(): void
    {
        $rules = new CastProblems();
        self::assertNull($rules->returning(null));
        self::assertNull($rules->returning(new CastTarget(CastKind::Signed)));
        self::assertNull($rules->target(new CastTarget(CastKind::Point), false));
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast(CastKind::Point), $rules->returning(new CastTarget(CastKind::Point)));
        self::assertEquals(new TooBigPrecision(7, 'CAST'), $rules->returning(new CastTarget(CastKind::Time, '7')));
    }

    public function testPrecisionRetainsTemporalLimitsAndFloatingOverflow(): void
    {
        $rules = new CastProblems();

        self::assertNull($rules->precision(new CastTarget(CastKind::Time, '6')));
        self::assertEquals(new TooBigPrecision(7, 'CAST'), $rules->precision(new CastTarget(CastKind::Time, '7')));
        self::assertEquals(new TooBigPrecision(-1, 'CAST', 53), $rules->precision(new CastTarget(CastKind::Float, '18446744073709551615')));
    }

    public function testTargetPrefersAnUnsupportedElementKindToItsPrecision(): void
    {
        $rules = new CastProblems();
        $target = new CastTarget(CastKind::Float, '54');

        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('CAST-ing data to array of FLOAT'), $rules->target($target, true));
        self::assertEquals(new TooBigPrecision(54, 'CAST', 53), $rules->target($target, false));
        self::assertEquals(new TooBigPrecision(7, 'CAST'), $rules->target(new CastTarget(CastKind::Time, '7'), true));
        self::assertNull($rules->target(new CastTarget(CastKind::Time, '6'), true));
    }

    public function testArrayDistinguishesIndexTargetsFromOrdinaryCasts(): void
    {
        $rules = new CastProblems();

        self::assertNull($rules->array(new CastTarget(CastKind::Signed)));
        self::assertNull($rules->array(new CastTarget(CastKind::Char, '4')));
        self::assertSame('CAST-ing data to array of char/binary BLOBs', $rules->array(new CastTarget(CastKind::Char)));
        self::assertSame('specifying charset for multi-valued index', $rules->array(new CastTarget(CastKind::NationalChar, '4')));
    }
}

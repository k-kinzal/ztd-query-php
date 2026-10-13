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
    public function testPrecisionRetainsTemporalLimitsAndFloatingOverflow(): void
    {
        $rules = new CastProblems();

        self::assertNull($rules->precision(new CastTarget(CastKind::Time, '6')));
        self::assertEquals(new TooBigPrecision(7, 'CAST'), $rules->precision(new CastTarget(CastKind::Time, '7')));
        self::assertEquals(new TooBigPrecision(-1, 'CAST', 53), $rules->precision(new CastTarget(CastKind::Float, '18446744073709551615')));
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

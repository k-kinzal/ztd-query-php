<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind as Kind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(ForeignKeyScalarLowering::class)]
final class ForeignKeyScalarLoweringTest extends TestCase
{
    public function testStorageSettlesAliasesAndNativeDefaultDimensions(): void
    {
        $lowering = new ForeignKeyScalarLowering();
        $release = GrammarRelease::MySql5651;

        self::assertEquals(new ForeignKeyScalarStorage(Kind::Decimal, 10, 0), $lowering->storage(new Decimal('0', '0'), $release));
        self::assertEquals(new ForeignKeyScalarStorage(Kind::Bit, 1), $lowering->storage(new Elementary(ElementaryKind::Bit, '0'), $release));
        self::assertEquals(new ForeignKeyScalarStorage(Kind::Double), $lowering->storage(new Floating(FloatingKind::Float, '25'), $release));
        self::assertEquals(new ForeignKeyScalarStorage(Kind::Year), $lowering->storage(new Temporal(TemporalKind::Year, '2'), $release));
        self::assertEquals(new ForeignKeyScalarStorage(Kind::Timestamp, 0), $lowering->storage(new Temporal(TemporalKind::Timestamp), $release));
    }

    public function testApproximateDistinguishesSingleFromDoubleAndKeepsDisplayDimensions(): void
    {
        $lowering = new ForeignKeyScalarLowering();

        self::assertEquals(new ForeignKeyScalarStorage(Kind::Single), $lowering->approximate(new Floating(FloatingKind::Float, '24')));
        self::assertEquals(new ForeignKeyScalarStorage(Kind::Double), $lowering->approximate(new Floating(FloatingKind::Real)));
        self::assertEquals(
            new ForeignKeyScalarStorage(Kind::Single, 12, 2),
            $lowering->approximate(new Floating(FloatingKind::Float, '12', '2'))
        );
    }

    public function testTemporalRetainsTheFractionalSecondsPrecision(): void
    {
        $storage = (new ForeignKeyScalarLowering())->temporal(
            new Temporal(TemporalKind::DateTime, '5'),
            GrammarRelease::MySql910
        );

        self::assertEquals(new ForeignKeyScalarStorage(Kind::DateTime, 5), $storage);
    }

    public function testFlagsResolvesZerofillAndItsUnsignedRange(): void
    {
        $lowering = new ForeignKeyScalarLowering();

        self::assertSame([true, true], $lowering->flags([NumericModifier::Zerofill]));
        self::assertSame([true, false], $lowering->flags([NumericModifier::Unsigned, NumericModifier::Signed]));
        self::assertSame([false, false], $lowering->flags([NumericModifier::Signed]));
    }

    public function testDimensionResolvesLeadingZeroesAndAbsence(): void
    {
        $lowering = new ForeignKeyScalarLowering();

        self::assertSame(6, $lowering->dimension('006'));
        self::assertNull($lowering->dimension(null));
    }
}

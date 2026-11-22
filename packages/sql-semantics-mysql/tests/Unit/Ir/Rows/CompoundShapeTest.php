<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\Compound;
use SqlSemantics\Platform\MySql\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\MySql\Ir\Rows\CompoundShape;
use SqlSemantics\Platform\MySql\Ir\Rows\ResultKind;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(CompoundShape::class)]
#[Small]
final class CompoundShapeTest extends TestCase
{
    public function testWidthDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(1, CompoundShape::width($value));
    }

    public function testDepthDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(1, CompoundShape::depth($value));
    }

    public function testArmsDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(2, CompoundShape::arms($value));
    }

    public function testTypeDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(ResultKind::WideInteger, CompoundShape::type($value, 0));
    }

    public function testSqlDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame('(' . $value->toString() . ')', CompoundShape::sql($value));
    }

    public function testLegacyDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(false, CompoundShape::legacy($value));
    }

    public function testMergeUsesTheKnownIntegerRole(): void
    {
        self::assertSame(ResultKind::Integer, CompoundShape::merge(ResultKind::Unknown, ResultKind::Integer, false));
    }

    public function testMergeKeepsLegacyFirstArmSignednessAndModernDecimalPromotion(): void
    {
        self::assertSame(ResultKind::UnsignedInteger, CompoundShape::merge(ResultKind::UnsignedInteger, ResultKind::WideInteger, true));
        self::assertSame(ResultKind::WideInteger, CompoundShape::merge(ResultKind::WideInteger, ResultKind::UnsignedInteger, true));
        self::assertSame(ResultKind::DecimalInteger, CompoundShape::merge(ResultKind::UnsignedInteger, ResultKind::WideInteger, false));
        self::assertSame(ResultKind::UnsignedInteger, CompoundShape::merge(ResultKind::UnsignedInteger, ResultKind::Unknown, false));
    }

    public function testTypeResolvesTwoBareNullArms(): void
    {
        $row = new Select([new ResultColumn(new NullLiteral(), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(ResultKind::NullOnly, CompoundShape::type($value, 0));
    }
}

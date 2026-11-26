<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Compound;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundShape;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\ResultKind;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

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

        self::assertSame(ResultKind::Integer, CompoundShape::type($value, 0));
    }

    public function testSqlDescribesTheClosedPair(): void
    {
        $row = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame('(' . $value->toString() . ')', CompoundShape::sql($value));
    }

    public function testMergePromotesEitherOrderOfNativeIntegerTypes(): void
    {
        self::assertSame(ResultKind::WideInteger, CompoundShape::merge(ResultKind::Integer, ResultKind::WideInteger));
        self::assertSame(ResultKind::WideInteger, CompoundShape::merge(ResultKind::WideInteger, ResultKind::Integer));
    }

    public function testMergeUsesTheKnownIntegerRole(): void
    {
        self::assertSame(ResultKind::Integer, CompoundShape::merge(ResultKind::Unknown, ResultKind::Integer));
    }
    public function testTypeResolvesTwoBareNullArms(): void
    {
        $row = new Select([new ResultColumn(new NullLiteral(), new Identifier('a'))]);
        $value = new Compound($row, CompoundOperator::Union, true, $row);

        self::assertSame(ResultKind::Text, CompoundShape::type($value, 0));
    }

}

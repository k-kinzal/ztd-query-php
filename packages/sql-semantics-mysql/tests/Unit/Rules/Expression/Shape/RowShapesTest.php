<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(RowShapes::class)]
#[Medium]
final class RowShapesTest extends TestCase
{
    public function testPartsPreservesNestedRowsAndScalarLeaves(): void
    {
        $inner = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $row = new Row([$inner, new NumberLiteral('3')]);
        self::assertSame($row->elements, (new RowShapes())->parts($row));
        self::assertSame([], (new RowShapes())->parts(new NumberLiteral('1')));
    }

    public function testProblemFindsNestedShapeContradictions(): void
    {
        $left = new Row([new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')])]);
        $right = new Row([new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3')]);
        $problem = (new RowShapes())->problem($left, $right);
        self::assertNotNull($problem);
        self::assertSame([1,2], [$problem->expected, $problem->actual]);
    }

    public function testQueryKeepsWildcardOutputDependentOnTheCatalog(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]);
        self::assertNull((new RowShapes())->query(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), $query));
    }

    public function testQueryDoesNotTreatZeroOutputColumnsAsOneScalar(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
        $problem = (new RowShapes())->query(new NumberLiteral('1'), $query);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse::class, $problem);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::EmptyValuesRow, $problem->rule);
    }

    public function testQueryKeepsUnknownWidthFlatAtEveryOutputColumn(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]);
        $operand = new Row([new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3'), new NumberLiteral('4')])]);
        $problem = (new RowShapes())->query($operand, $query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([3, 1], [$problem->expected, $problem->actual]);
    }

    public function testPairsDistinguishesUnknownWidthFromOneValue(): void
    {
        self::assertNull((new RowShapes())->pairs(null, [null, null]));
        self::assertNotNull((new RowShapes())->pairs([], [null, null]));
        self::assertNull((new RowShapes())->pairs([null, null], [null, null]));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OutputOrdinal::class)]
#[Medium]
final class OutputOrdinalTest extends TestCase
{
    public function testPositionCountsFromOne(): void
    {
        self::assertSame(1, (new OutputOrdinal(new IntegerLiteral('1')))->position());
        self::assertSame(12, (new OutputOrdinal(new IntegerLiteral('0012')))->position());
        self::assertSame(-2, (new OutputOrdinal(new Unary(UnaryOperator::Minus, new IntegerLiteral('2'))))->position());
        self::assertSame(3, (new OutputOrdinal(new Collate(new Grouped(new HexLiteral('3')), new Name('nocase'))))->position());
    }

    public function testDeriveScalarDenotesTheResultColumnAtThePosition(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 'x' AS a, 2 AS b ORDER BY 2, +1");

        self::assertInstanceOf(Select::class, $query->statement);
        $second = $query->statement->orderBy[0]->expression;
        $first = $query->statement->orderBy[1]->expression;
        self::assertInstanceOf(OutputOrdinal::class, $second);
        self::assertInstanceOf(OutputOrdinal::class, $first);
        $fact = $query->facts->scalar($second);
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($query->field('b'), $fact->resolution->field);
        self::assertSame($query->field('b')->type, $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        $resolution = $query->facts->scalar($first)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame(0, $resolution->field->position);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarAlsoRecordsTheConstant(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 GROUP BY 1');

        self::assertInstanceOf(Select::class, $query->statement);
        $ordinal = $query->statement->groupBy[0];
        self::assertInstanceOf(OutputOrdinal::class, $ordinal);
        self::assertTrue($query->facts->covers($ordinal->constant));
        self::assertSame(Nullability::NotNull, $query->facts->scalar($ordinal->constant)->nullability);
    }

    public function testDeriveScalarReportsAPositionOutsideTheResultColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $beyond = $semantics->analyze('SELECT 1 ORDER BY 2');
        $negative = $semantics->analyze('SELECT 1 AS a, 2 AS b ORDER BY -1');

        self::assertInstanceOf(Select::class, $beyond->statement);
        $fact = $beyond->facts->scalar($beyond->statement->orderBy[0]->expression);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(OrdinalOutOfRange::class, $fact->resolution);
        self::assertSame(2, $fact->resolution->ordinal);
        self::assertSame(1, $fact->resolution->columns);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertSame('Term 2 is out of range - should be between 1 and 1.', $beyond->facts->diagnostics[0]->message());
        self::assertSame('Term -1 is out of range - should be between 1 and 2.', $negative->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarDependsOnTheMissingInputsBeyondTheKnownColumns(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, * FROM t ORDER BY 3');

        self::assertInstanceOf(Select::class, $query->statement);
        $fact = $query->facts->scalar($query->statement->orderBy[0]->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the declaration of relation t', $fact->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarCountsTheColumnsOfACompound(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS b UNION SELECT 3, 4 ORDER BY 2');

        self::assertInstanceOf(Compound::class, $query->statement);
        $fact = $query->facts->scalar($query->statement->orderBy[0]->expression);
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame('b', $fact->resolution->field->name?->value);
    }

    public function testRenderWritesTheConstantAsWritten(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select 1 as a, 2 as b order by (1) collate nocase, 0x2, +1');

        self::assertSame('SELECT 1 AS a, 2 AS b ORDER BY (1) COLLATE nocase, 0x2, + 1', $query->toString());
    }

    public function testRenderRefusesAnExpressionThatIsNoIntegerConstant(): void
    {
        $this->expectExceptionMessage('A result column position is written as an integer constant.');

        new OutputOrdinal(new TextLiteral('1'));
    }

    public function testRenderRefusesAnIntegerBeyondThirtyTwoBits(): void
    {
        $this->expectExceptionMessage('A result column position is written as an integer constant.');

        new OutputOrdinal(new IntegerLiteral('2147483648'));
    }
}

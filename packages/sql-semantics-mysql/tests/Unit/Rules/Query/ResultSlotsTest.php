<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\ResultSlots;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ResultSlots::class)]
#[Medium]
final class ResultSlotsTest extends TestCase
{
    public function testCombineNamesAfterTheLeftOperand(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $left = new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull))], $semantics->context()->columnNames);
        $right = new QueryFact([new Field(0, new OutputSlot(new Name('b'), new Known(new Integral(IntegralKind::Int)), Nullability::Nullable))], $semantics->context()->columnNames);
        $fields = (new ResultSlots())->combine($left, $right, SetOperator::Union, $derivation);

        self::assertInstanceOf(Field::class, $fields[0]);
        self::assertSame('a', $fields[0]->name?->value);
        self::assertSame(Nullability::Nullable, $fields[0]->nullability);
    }

    public function testNullabilityFollowsTheOperator(): void
    {
        self::assertSame(Nullability::Nullable, (new ResultSlots())->nullability(SetOperator::Union, Nullability::NotNull, Nullability::Nullable));
        self::assertSame(Nullability::NotNull, (new ResultSlots())->nullability(SetOperator::Intersect, Nullability::NotNull, Nullability::Nullable));
        self::assertSame(Nullability::Nullable, (new ResultSlots())->nullability(SetOperator::Intersect, Nullability::Nullable, Nullability::Nullable));
        self::assertSame(Nullability::Dependent, (new ResultSlots())->nullability(SetOperator::Intersect, Nullability::Dependent, Nullability::Nullable));
        self::assertSame(Nullability::NotNull, (new ResultSlots())->nullability(SetOperator::Except, Nullability::NotNull, Nullability::Nullable));
    }

    public function testValuesReportsARowAsAValue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('VALUES ROW((1, 2))');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testValuesNumbersTheRowOfAnotherLengthAndReportsAnEmptyRow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $longer = $semantics->analyze('VALUES ROW(1), ROW(2), ROW(3, 4)')->facts->diagnostics;
        $empty = $semantics->analyze('VALUES ROW(), ROW(1)')->facts->diagnostics;

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch::class, $longer[0]);
        self::assertSame(3, $longer[0]->row);
        self::assertSame(['Each row of a VALUES clause must have at least one column, unless when used as source in an INSERT statement.', "Column count doesn't match value count (0 and 1)."], array_map(static fn ($diagnostic): string => $diagnostic->message(), $empty));
    }

    public function testValuesAcceptsAnEmptyRowAndDefaultInTheRowsAStatementWrites(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $values = new ValuesQuery([new ValueRow([]), new ValueRow([])]);
        $derivation->writes($values, [], true);
        (new ResultSlots())->values($values, $derivation, $derivation->environment());
        $t = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame([], $derivation->facts()->diagnostics);
        self::assertSame([], $semantics->analyze('INSERT INTO t (a, b) (VALUES ROW(DEFAULT, 1))', [$t])->facts->diagnostics);
    }

    public function testSettledCountsTheBytesOfATextOnce(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (b TEXT)');
        $once = $semantics->analyze('SELECT b FROM t UNION SELECT b FROM t', [$t])->facts->output?->fields()?->at(0)->type;
        $nested = $semantics->analyze('SELECT b FROM t UNION SELECT b FROM t EXCEPT SELECT b FROM t', [$t])->facts->output?->fields()?->at(0)->type;

        self::assertInstanceOf(Known::class, $once);
        self::assertEquals($once, $nested);
    }

    public function testValuesNamesTheColumnsByPosition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $fact = (new ResultSlots())->values(new ValuesQuery([new ValueRow([new NumberLiteral('1'), new NullLiteral()])]), $derivation, $derivation->environment());

        self::assertSame(['column_0', 'column_1'], array_map(static fn (Field $field): ?string => $field->name?->value, $fact->fields()->items ?? []));
        self::assertSame(Nullability::Nullable, $fact->fields()?->at(1)->nullability);
    }

    public function testCombineKeepsTheInputsAnUnnamedLeftColumnDependsOn(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT x FROM (SELECT 'é' UNION SELECT 1) AS d");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Dependent::class, $operation->field(0)->type);
    }

    public function testSettledLeavesOutAColumnNullInEveryRowOfANestedOperation(): void
    {
        $type = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION (SELECT NULL INTERSECT SELECT NULL)')->facts->output?->fields()?->at(0)->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $type->descriptor);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer, $type->descriptor->kind);
    }
}

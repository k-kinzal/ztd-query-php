<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SetOperation::class)]
#[Medium]
final class SetOperationTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsAsTheOutput(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT a, b FROM t UNION ALL SELECT a, c FROM u', [$t, $u]);

        self::assertInstanceOf(SetOperation::class, $operation->statement);
        self::assertSame($operation->facts->query($operation->statement), $operation->facts->output);
        self::assertSame(SetQuantifier::All, $operation->statement->quantifier);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveQueryNamesTheColumnsAfterTheFirstOperandAndCombinesNullability(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $union = $semantics->analyze('SELECT a, b FROM t UNION SELECT a, c FROM u', [$t, $u]);
        $intersect = $semantics->analyze('SELECT a, b FROM t INTERSECT SELECT a, c FROM u', [$t, $u]);
        $except = $semantics->analyze('SELECT a, c FROM u EXCEPT SELECT a, b FROM t', [$t, $u]);

        self::assertSame(['a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $union->fields()->items ?? []));
        self::assertSame(Nullability::Nullable, $union->field('b')->nullability);
        self::assertSame(Nullability::NotNull, $intersect->field('b')->nullability);
        self::assertSame(Nullability::NotNull, $except->field('c')->nullability);
        self::assertInstanceOf(Known::class, $union->field('a')->type);
        self::assertNull($union->field('a')->column());
    }

    public function testDeriveQueryReportsOperandsOfDifferentLengths(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION SELECT 1, 2');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(CountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertSame(CountedList::SetOperands, $operation->facts->diagnostics[0]->list);
    }

    public function testDeriveQueryLeavesTheOutputOpenOverAnUndeclaredFirstOperand(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM t UNION SELECT 1');

        self::assertNull($operation->fields());
        self::assertNotNull($operation->shape());
        self::assertFalse($operation->shape()->complete());
    }

    public function testRenderWritesTheOperandsLeftDeepAndKeepsParentheses(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT 1 UNION SELECT 2 EXCEPT DISTINCT SELECT 3', $semantics->analyze('select 1 union select 2 except distinct select 3')->toString());
        self::assertSame('SELECT 1 UNION SELECT 2 INTERSECT SELECT 3', $semantics->analyze('SELECT 1 UNION SELECT 2 INTERSECT SELECT 3')->toString());
        self::assertSame('SELECT 1 INTERSECT (SELECT 2 UNION SELECT 3)', $semantics->analyze('SELECT 1 INTERSECT (SELECT 2 UNION SELECT 3)')->toString());
        self::assertInstanceOf(SetOperation::class, $semantics->analyze('SELECT 1 UNION SELECT 2 INTERSECT SELECT 3')->statement);
    }

    public function testANestedOperationOnTheRightIsRejected(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $two = new Select([], [new SelectExpression(new NumberLiteral('2'))]);
        $three = new Select([], [new SelectExpression(new NumberLiteral('3'))]);

        $this->expectExceptionMessage('A set operation on the right of another is written in parentheses unless it binds more tightly.');

        new SetOperation($one, SetOperator::Union, null, new SetOperation($two, SetOperator::Except, null, $three));
    }

    public function testALooserOperationOnTheLeftOfIntersectIsRejected(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $two = new Select([], [new SelectExpression(new NumberLiteral('2'))]);
        $three = new Select([], [new SelectExpression(new NumberLiteral('3'))]);

        $this->expectExceptionMessage('A looser set operation on the left of INTERSECT is written in parentheses.');

        new SetOperation(new SetOperation($one, SetOperator::Union, null, $two), SetOperator::Intersect, null, $three);
    }

    public function testALastOperandWithItsOwnOrderingIsRejected(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $two = new Select([], [new SelectExpression(new ColumnUse(new Name('a')))], null, null, null, null, [], null, [new OrderItem(new ColumnUse(new Name('a')))]);

        $this->expectExceptionMessage('The last operand of a set operation has no ORDER BY, LIMIT, INTO or locking clause of its own unless it is written in parentheses.');

        new SetOperation($one, SetOperator::Union, null, $two);
    }

    public function testAQueryExpressionOnTheRightIsRejected(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $two = new Select([], [new SelectExpression(new NumberLiteral('2'))]);

        $this->expectExceptionMessage('A query with a WITH clause or with ordering of its own is written in parentheses as a set operand.');

        new SetOperation($one, SetOperator::Union, null, new QueryExpression(null, new ParenthesizedQuery($two), [], new RowLimit(new NumberLiteral('1'))));
    }

    public function testAQueryStatementAsAnOperandIsRejected(): void
    {
        $one = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $two = new Select([], [new SelectExpression(new NumberLiteral('2'))]);

        $this->expectExceptionMessage('A query with INTO or locking clauses is written in parentheses as a set operand.');

        new SetOperation(new QueryStatement(new ParenthesizedQuery($one), [new LockingClause(LockStrength::Update)]), SetOperator::Union, null, $two);
    }
}

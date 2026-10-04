<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Comparison::class)]
#[Medium]
final class ComparisonTest extends TestCase
{
    public function testDeriveScalarYieldsAnIntegerThatCanBeNullWhenAnOperandCan(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT a FROM t WHERE b = 1', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        $fact = $operation->facts->scalar($operation->statement->where);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame('BIGINT', $fact->type->descriptor->name());
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarNeverYieldsNullWhenBothOperandsCannot(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT a FROM t WHERE 1 < a', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($operation->statement->where)->nullability);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($operation->statement->where->left)->nullability);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($operation->statement->where->right)->nullability);
    }

    public function testDeriveScalarNeverYieldsNullForTheNullSafeEquality(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT b FROM t WHERE b <=> NULL', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertSame(ComparisonOperator::NullSafeEqual, $operation->statement->where->operator);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($operation->statement->where)->nullability);
        self::assertSame(Nullability::Nullable, $operation->facts->scalar($operation->statement->where->left)->nullability);
    }

    public function testDeriveScalarDependsOnTheOperandsOfAnUndeclaredTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a >= 10');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        $fact = $operation->facts->scalar($operation->statement->where);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        $left = $operation->facts->scalar($operation->statement->where->left);
        self::assertInstanceOf(Dependent::class, $left->type);
        self::assertSame('the declaration of relation t', $left->type->missing[0]->describe());
    }

    public function testRenderWritesTheOperandsAroundTheOperatorInTheOrderWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select a from t where 10 <= a');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertInstanceOf(NumberLiteral::class, $operation->statement->where->left);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->where->right);
        self::assertSame('SELECT a FROM t WHERE 10 <= a', $operation->toString());
    }

    public function testRenderSpellsTheNotEqualOperatorInItsStandardForm(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a != 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertSame(ComparisonOperator::NotEqual, $operation->statement->where->operator);
        self::assertSame('SELECT a FROM t WHERE a <> 1', $operation->toString());
    }

    public function testRenderKeepsTheLeftAssociationOfAChain(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE 1 < a = 2');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertSame(ComparisonOperator::Equal, $operation->statement->where->operator);
        self::assertInstanceOf(Comparison::class, $operation->statement->where->left);
        self::assertSame(ComparisonOperator::Less, $operation->statement->where->left->operator);
        self::assertSame('SELECT a FROM t WHERE 1 < a = 2', $operation->toString());
    }

    public function testRenderIsTheSameInEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT a FROM t WHERE a <=> 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t where a <=> 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a <=> 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a from t where a <=> 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a <=> 1', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('select a from t where a <=> 1')->toString());
    }

    public function testAComparisonAsRightOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The right operand of a comparison needs a grouping to keep its place.');

        new Comparison(ComparisonOperator::Equal, new ColumnUse(new Name('a')), new Comparison(ComparisonOperator::Less, new NumberLiteral('1'), new NumberLiteral('2')));
    }

    public function testAVariableAssignmentAsLeftOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The left operand of a comparison needs a grouping to keep its place.');

        new Comparison(ComparisonOperator::Equal, new VariableAssignment(new UserVariable(new Name('n')), new NumberLiteral('1')), new NumberLiteral('2'));
    }
}

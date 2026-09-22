<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Operator\UnaryExpression;
use SqlSemantics\Model\Scalar\Operator\UnaryOperator;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(UnaryExpression::class)]
final class UnaryExpressionTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTruthTests')]
    public function testInputsRetainsOnePredicateOperand(Dialect $dialect, UnaryOperator $operator): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind('SELECT NULL ' . $operator->value);
        self::assertInstanceOf(BoundSelect::class, $query);
        $predicate = $query->outputs[0]->expression;
        self::assertInstanceOf(UnaryExpression::class, $predicate);
        self::assertSame($operator, $predicate->operator);
        self::assertSame([$predicate->operand], $predicate->inputs());
        self::assertSame(Nullability::NotNull, $predicate->nullability);
        self::assertSame('SELECT (NULL ' . $operator->value . ')', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    /**
     * @return list<array{Dialect, UnaryOperator}>
     */
    public static function providerTruthTests(): array
    {
        return array_merge(...array_map(static fn (Dialect $dialect): array => array_map(static fn (UnaryOperator $operator): array => [$dialect, $operator], [UnaryOperator::IsTrue, UnaryOperator::IsNotTrue, UnaryOperator::IsFalse, UnaryOperator::IsNotFalse, UnaryOperator::IsUnknown, UnaryOperator::IsNotUnknown]), [Dialect::PostgreSql, Dialect::MySql]));
    }

    public function testWithFactsRejectsANullableTruthResult(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT NULL IS TRUE');
        self::assertInstanceOf(BoundSelect::class, $query);
        $predicate = $query->outputs[0]->expression;
        self::assertInstanceOf(UnaryExpression::class, $predicate);
        $this->expectException(InvalidStructure::class);
        $predicate->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($predicate->type, Nullability::MaybeNull));
    }

    public function testWithFactsPreservesTheOperatorAndOperand(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT NULL IS NOT FALSE');
        self::assertInstanceOf(BoundSelect::class, $query);
        $predicate = $query->outputs[0]->expression;
        self::assertInstanceOf(UnaryExpression::class, $predicate);
        $copy = $predicate->withFacts($predicate->facts);
        self::assertNotSame($predicate, $copy);
        self::assertSame(UnaryOperator::IsNotFalse, $copy->operator);
        self::assertSame($predicate->operand, $copy->operand);
    }

    public function testSpellingRetainsTruthAndNegation(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT TRUE IS NOT UNKNOWN');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame('IS NOT UNKNOWN', $query->outputs[0]->expression->spelling());
    }

    public function testInputsRequiresABooleanInPostgreSql(): void
    {
        $this->expectException(\SqlSemantics\SemanticException::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 IS TRUE');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\QueryExpressionBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(QueryExpressionBinder::class)]
#[Medium]
final class QueryExpressionBinderTest extends TestCase
{
    public function testBindClassifiesEveryQueryOperandForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT (SELECT 1), a IN (SELECT a FROM t), EXISTS (SELECT 1), a = ANY (SELECT a FROM t), a NOT IN (SELECT a FROM t), a < ALL (SELECT a FROM t) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        [$scalar, $in, $exists, $any, $notIn, $all] = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $scalar);
        self::assertSame('integer', $scalar->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $scalar->nullability);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\InSubquery::class, $in);
        self::assertFalse($in->negated);
        self::assertSame('boolean', $in->type->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ExistsSubquery::class, $exists);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $exists->nullability);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\QuantifiedComparison::class, $any);
        self::assertSame(\SqlSemantics\Model\Scalar\Query\ComparisonOperator::Equal, $any->operator);
        self::assertSame(\SqlSemantics\Model\Scalar\Query\Quantifier::Any, $any->quantifier);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\InSubquery::class, $notIn);
        self::assertTrue($notIn->negated);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\QuantifiedComparison::class, $all);
        self::assertSame(\SqlSemantics\Model\Scalar\Query\ComparisonOperator::Less, $all->operator);
        self::assertSame(\SqlSemantics\Model\Scalar\Query\Quantifier::All, $all->quantifier);
    }

    public function testScalarBuildsARowSubqueryOnlyForARowComparisonOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT (a, a) = (SELECT 1, 2) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $comparison = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $comparison);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\RowSubquery::class, $comparison->right);
        self::assertSame('record', $comparison->right->type->name);
        self::assertCount(2, $comparison->right->query->resultColumns());
        self::assertSame('SELECT (ROW("a", "a") = (SELECT 1, 2)) FROM "public"."t"', $statement->toString());
    }

    public function testScalarRejectsAWideQueryInScalarPosition(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ScalarQueryWidth->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT (SELECT 1, 2) FROM t');
    }

    public function testComparisonRejectsMismatchedWidths(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ComparisonWidth->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT (a, a) IN (SELECT 1) FROM t');
    }

    public function testComparisonRejectsAnArithmeticQuantifiedOperator(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::QuantifiedOperator->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 + ANY (SELECT 1)');
    }

    public function testComparisonKeepsAPatternOrNamedQuantifiedOperator(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT 'a' NOT LIKE ALL (SELECT 'b'), 1 OPERATOR(geo.<->) SOME (SELECT 2)");
        self::assertSame("SELECT ('a' NOT LIKE ALL (SELECT 'b')), (1 OPERATOR(\"geo\".<->) SOME(SELECT 2))", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}

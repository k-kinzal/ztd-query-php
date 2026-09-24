<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Query\ExistsSubquery;
use SqlSemantics\Model\Scalar\Query\InSubquery;
use SqlSemantics\Model\Scalar\Query\QuantifiedComparison;
use SqlSemantics\Model\Scalar\Query\RowSubquery;
use SqlSemantics\Model\Scalar\Query\ScalarSubquery;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\Subqueries;

#[CoversClass(Subqueries::class)]
#[Medium]
final class SubqueriesTest extends TestCase
{
    #[TestWith(['SELECT id FROM t WHERE id = ANY (SELECT id FROM s)', 'SELECT "id" AS "id" FROM "public"."t" WHERE ("id" = ANY(SELECT "id" AS "id" FROM "public"."s"))'])]
    #[TestWith(['SELECT id FROM t WHERE id > ALL (SELECT id FROM s)', 'SELECT "id" AS "id" FROM "public"."t" WHERE ("id" > ALL (SELECT "id" AS "id" FROM "public"."s"))'])]
    #[TestWith(['SELECT id FROM t WHERE id NOT IN (SELECT id FROM s)', 'SELECT "id" AS "id" FROM "public"."t" WHERE ("id" NOT IN(SELECT "id" AS "id" FROM "public"."s"))'])]
    #[TestWith(['SELECT id FROM t WHERE id IN (SELECT id FROM s)', 'SELECT "id" AS "id" FROM "public"."t" WHERE ("id" IN (SELECT "id" AS "id" FROM "public"."s"))'])]
    #[TestWith(['SELECT id FROM t WHERE EXISTS (SELECT 1)', 'SELECT "id" AS "id" FROM "public"."t" WHERE EXISTS(SELECT 1)'])]
    #[TestWith(['SELECT (SELECT 1)', 'SELECT (SELECT 1)'])]
    #[TestWith(['SELECT (id, n) = (SELECT 1, 2) FROM t', 'SELECT (ROW("id", "n") = (SELECT 1, 2)) FROM "public"."t"'])]
    public function testWriteKeepsEachSubqueryInItsOperandPosition(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testWriteSpellsScalarAndRowSubqueriesAsParenthesizedQueries(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)')))->bind('SELECT (SELECT 1), (id, n) = (SELECT 1, 2) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $scalar = $statement->outputs[0]->expression;
        self::assertInstanceOf(ScalarSubquery::class, $scalar);
        self::assertSame('(SELECT 1)', Subqueries::write($scalar)->toString());
        $comparison = $statement->outputs[1]->expression;
        self::assertInstanceOf(BinaryExpression::class, $comparison);
        $row = $comparison->right;
        self::assertInstanceOf(RowSubquery::class, $row);
        self::assertSame('(SELECT 1, 2)', Subqueries::write($row)->toString());
    }

    public function testWriteSpellsPredicatesWithTheirValueOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE s(id INT)'));
        $exists = $binder->bind('SELECT id FROM t WHERE EXISTS (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $exists);
        self::assertInstanceOf(ExistsSubquery::class, $exists->where);
        self::assertSame('EXISTS(SELECT 1)', Subqueries::write($exists->where)->toString());
        $membership = $binder->bind('SELECT id FROM t WHERE id NOT IN (SELECT id FROM s)');
        self::assertInstanceOf(BoundSelect::class, $membership);
        self::assertInstanceOf(InSubquery::class, $membership->where);
        self::assertTrue($membership->where->negated);
        self::assertSame('("id" NOT IN(SELECT "id" AS "id" FROM "public"."s"))', Subqueries::write($membership->where)->toString());
        $quantified = $binder->bind('SELECT id FROM t WHERE id = ANY (SELECT id FROM s)');
        self::assertInstanceOf(BoundSelect::class, $quantified);
        self::assertInstanceOf(QuantifiedComparison::class, $quantified->where);
        self::assertSame('("id" = ANY(SELECT "id" AS "id" FROM "public"."s"))', Subqueries::write($quantified->where)->toString());
    }

    public function testWriteKeepsTheArrayKeywordBeforeACollectedQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT ARRAY(SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ArraySubquery::class, $value);
        self::assertSame('ARRAY(SELECT 1)', Subqueries::write($value)->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}

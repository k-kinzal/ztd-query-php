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
use SqlSemantics\Model\Scalar\Conditional\Extremum;
use SqlSemantics\Model\Scalar\Conditional\InList;
use SqlSemantics\Model\Scalar\Conditional\SearchedCase;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\ConditionalExpressions;

#[CoversClass(ConditionalExpressions::class)]
#[Medium]
final class ConditionalExpressionsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT GREATEST(1,2), LEAST(1,2)', 'SELECT GREATEST(1, 2), LEAST(1, 2)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT COALESCE(1,2), NULLIF(1,2)', 'SELECT COALESCE(1, 2), NULLIF(1, 2)'])]
    #[TestWith([Dialect::PostgreSql, "SELECT CASE id WHEN 1 THEN 'a' ELSE 'b' END, CASE WHEN id > 1 THEN 'a' END FROM t", "SELECT CASE \"id\" WHEN 1 THEN 'a' ELSE 'b' END, CASE WHEN (\"id\" > 1) THEN 'a' END FROM \"public\".\"t\""])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id BETWEEN SYMMETRIC 1 AND 2, id NOT BETWEEN 1 AND 2 FROM t', 'SELECT ("id" BETWEEN SYMMETRIC 1 AND 2), ("id" NOT BETWEEN 1 AND 2) FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, "SELECT 'a' LIKE 'b' ESCAPE '!', 'a' NOT ILIKE 'b', 'a' SIMILAR TO 'b'", "SELECT ('a' LIKE 'b' ESCAPE '!'), ('a' NOT ILIKE 'b'), ('a' SIMILAR TO 'b')"])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 NOT IN (2, 3), 1 IN (2)', 'SELECT (1 NOT IN(2, 3)), (1 IN (2))'])]
    #[TestWith([Dialect::Sqlite, "SELECT 1 GLOB 'a', 1 NOT LIKE 'a'", "SELECT (1 GLOB 'a'), (1 NOT LIKE 'a')"])]
    #[TestWith([Dialect::MySql, "SELECT 1 MEMBER OF('[1]')", "SELECT (1 MEMBER OF('[1]'))"])]
    public function testWriteKeepsPredicateAndResultRolesForEachConditionalForm(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testWriteSpellsAnExtremumWithItsSelectionKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT LEAST(1, 2, 3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Extremum::class, $value);
        self::assertSame('LEAST(1, 2, 3)', ConditionalExpressions::write($value)->toString());
    }

    public function testWriteParenthesizesAMembershipTestAndItsCandidates(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1 IN (2, 3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(InList::class, $value);
        self::assertSame('(1 IN (2, 3))', ConditionalExpressions::write($value)->toString());
    }

    public function testWriteOmitsTheElseBranchWhenAbsent(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT CASE WHEN 1 > 0 THEN 'a' WHEN 2 > 0 THEN 'b' END");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(SearchedCase::class, $value);
        self::assertNull($value->otherwise);
        self::assertSame("CASE WHEN (1 > 0) THEN 'a' WHEN (2 > 0) THEN 'b' END", ConditionalExpressions::write($value)->toString());
    }

    public function testWriteKeepsTheQuantifiedArrayOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT 'a' NOT LIKE ALL (ARRAY['b'])");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Conditional\ArrayComparison::class, $value);
        self::assertSame("('a' NOT LIKE ALL(ARRAY['b']))", ConditionalExpressions::write($value)->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testJsonSpellsTheJsonPredicateItemKind(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT '[]' IS NOT JSON ARRAY WITH UNIQUE KEYS");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $predicate = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Conditional\JsonPredicate::class, $predicate);
        self::assertSame("('[]' IS NOT JSON ARRAY WITH UNIQUE KEYS)", ConditionalExpressions::json($predicate)->toString());
    }

    #[TestWith(['SELECT a IS JSON OBJECT WITH UNIQUE KEYS FROM t', BoundSelect::class, 'SELECT ("a" IS JSON OBJECT WITH UNIQUE KEYS) FROM "public"."t"'])]
    #[TestWith(['SELECT a IS NOT JSON FROM t', BoundSelect::class, 'SELECT ("a" IS NOT JSON VALUE) FROM "public"."t"'])]
    public function testWriteSpellsJsonPredicates(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT)')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}

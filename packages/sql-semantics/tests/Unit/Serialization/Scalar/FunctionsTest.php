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
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\Functions;

#[CoversClass(Functions::class)]
#[Medium]
final class FunctionsTest extends TestCase
{
    /**
     * @param non-empty-list<string> $parts Qualified case-sensitive function identity
     */
    #[TestWith(['SELECT "MiXeD"(1)', ['MiXeD']])]
    #[TestWith(['SELECT "App"."MiXeD"(1)', ['App', 'MiXeD']])]
    #[TestWith(['SELECT "coalesce"(1)', ['coalesce']])]
    #[TestWith(['SELECT "greatest"(1)', ['greatest']])]
    #[TestWith(['SELECT "current_date"()', ['current_date']])]
    #[TestWith(['SELECT "select"(1)', ['select']])]
    public function testWritePreservesCaseSensitiveFunctionNames(string $sql, array $parts): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame($parts, $call->function->name()->parts);
        self::assertSame($sql, $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        $roundTrip = $rebound->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $roundTrip);
        self::assertSame($parts, $roundTrip->function->name()->parts);
    }

    #[TestWith(['mysql-8.2.0', 'SELECT `name` ( ) LIKE 1', 'SELECT (`name`() LIKE 1)'])]
    #[TestWith(['mysql-5.7.44', 'SELECT x(), LEFT(1, 2), `left`(1, 2)', 'SELECT `x`(), LEFT(1, 2), `left`(1, 2)'])]
    public function testWriteQuotesAMySqlFunctionFoundByName(string $release, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['SELECT f(a := 1)', 'SELECT "f"("a" => 1)'])]
    #[TestWith(['SELECT f(VARIADIC ARRAY[1])', 'SELECT "f"(VARIADIC ARRAY[1])'])]
    #[TestWith(['SELECT f(1, VARIADIC "Items" => ARRAY[1])', 'SELECT "f"(1, VARIADIC "Items" => ARRAY[1])'])]
    public function testArgumentWritesNamedAndVariadicNotation(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith([Dialect::Sqlite, 'SELECT abs(a), extract(a), "1abc"(1), "abc-"(1), "my fn"(1) FROM t', 'SELECT abs("a"), extract("a"), "1abc"(1), "abc-"(1), "my fn"(1) FROM "main"."t"'])]
    #[TestWith([Dialect::Sqlite, 'SELECT count(*) FILTER (WHERE a > 1), sum(a) OVER (PARTITION BY b) FROM t', 'SELECT count(*) FILTER (WHERE ("a" > 1)), sum("a") OVER (PARTITION BY "b") FROM "main"."t"'])]
    #[TestWith([Dialect::Sqlite, 'SELECT myagg(a) FILTER (WHERE a > 1), myagg() FILTER (WHERE a > 1), count(DISTINCT a) FROM t', 'SELECT myagg(ALL "a") FILTER (WHERE ("a" > 1)), myagg(ALL) FILTER (WHERE ("a" > 1)), count(DISTINCT "a") FROM "main"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY a) FROM t', 'SELECT "percentile_cont"(0.5) WITHIN GROUP(ORDER BY "a" ASC) FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT myagg(a ORDER BY a), myagg(*), count(DISTINCT a) FROM t', 'SELECT "myagg"(ALL "a" ORDER BY "a" ASC), "myagg"(*), "count"(DISTINCT "a") FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT string_agg(b, b ORDER BY a) FILTER (WHERE a > 1) FROM t', 'SELECT "string_agg"("b", "b" ORDER BY "a" ASC) FILTER (WHERE ("a" > 1)) FROM "public"."t"'])]
    #[TestWith([Dialect::MySql, 'SELECT myagg(a), abs(a), sum(a) OVER w FROM t WINDOW w AS ()', 'SELECT `myagg`(`a`), abs(`a`), sum(`a`) OVER `w` FROM `t` WINDOW `w` AS ()'])]
    public function testWriteSpellsEachInvocationForm(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INTEGER, b TEXT)'));
        self::assertSame($expected, $binder->bind($sql, strict: false)->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\CurrentRow;
use SqlSemantics\Model\Window\NamedWindow;
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\Unbounded;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\Windows;

#[CoversClass(Windows::class)]
#[Medium]
final class WindowsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) OVER (PARTITION BY id ORDER BY n ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE CURRENT ROW) FROM t', '(PARTITION BY "id" ORDER BY "n" ASC ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE CURRENT ROW)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) OVER (ORDER BY n GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) FROM t', '(ORDER BY "n" ASC GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES)'])]
    #[TestWith([Dialect::MySql, 'SELECT SUM(n) OVER (ORDER BY n RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM t', '(ORDER BY `n` ASC RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING)'])]
    #[TestWith([Dialect::MySql, 'SELECT SUM(n) OVER (w ORDER BY n) FROM t WINDOW w AS (PARTITION BY id)', '(`w` ORDER BY `n` ASC)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) OVER () FROM t', '()'])]
    public function testWriteSerializesPartitioningOrderingFrameAndExclusion(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        self::assertSame($expected, Windows::write($call->window, $dialect)->toString());
        self::assertStringContainsString(' OVER ' . $expected, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWriteQuotesANamedWindowReference(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('SELECT SUM(n) OVER w FROM t WINDOW w AS (PARTITION BY id)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(NamedWindow::class, $call->window);
        self::assertSame('"w"', Windows::write($call->window, Dialect::PostgreSql)->toString());
        self::assertSame('SELECT "sum"("n") OVER "w" FROM "public"."t" WINDOW "w" AS (PARTITION BY "id")', $statement->toString());
    }

    public function testBoundaryDistinguishesOffsetsFromTheUnboundedAndCurrentRowForms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)')))->bind('SELECT SUM(n) OVER (ORDER BY n ROWS BETWEEN 2 PRECEDING AND CURRENT ROW), SUM(n) OVER (ORDER BY n ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $first = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $first);
        self::assertInstanceOf(WindowSpecification::class, $first->window);
        self::assertNotNull($first->window->frame);
        self::assertInstanceOf(Offset::class, $first->window->frame->start);
        self::assertSame('2 PRECEDING', Windows::boundary($first->window->frame->start)->toString());
        self::assertInstanceOf(CurrentRow::class, $first->window->frame->end);
        self::assertSame('CURRENT ROW', Windows::boundary($first->window->frame->end)->toString());
        $second = $statement->outputs[1]->expression;
        self::assertInstanceOf(WindowCall::class, $second);
        self::assertInstanceOf(WindowSpecification::class, $second->window);
        self::assertNotNull($second->window->frame);
        self::assertInstanceOf(Unbounded::class, $second->window->frame->end);
        self::assertSame('UNBOUNDED FOLLOWING', Windows::boundary($second->window->frame->end)->toString());
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteSpellsEveryFrameBoundary')]
    public function testWriteSpellsEveryFrameBoundary(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteSpellsEveryFrameBoundary(): iterable
    {
        return [
            'select sum(a) over (order by a rows between unbounded preceding and current row) from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (order by a rows between unbounded preceding and current row) from t', 'SELECT "sum"("a") OVER (ORDER BY "a" ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM "public"."t"'],
            'select sum(a) over (order by a range between 1 preceding and 2 following exclude ties) from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (order by a range between 1 preceding and 2 following exclude ties) from t', 'SELECT "sum"("a") OVER (ORDER BY "a" ASC RANGE BETWEEN 1 PRECEDING AND 2 FOLLOWING EXCLUDE TIES) FROM "public"."t"'],
            'select sum(a) over (order by a groups between current row and unbounded following) from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (order by a groups between current row and unbounded following) from t', 'SELECT "sum"("a") OVER (ORDER BY "a" ASC GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM "public"."t"'],
            'select sum(a) over (order by a rows 3 preceding) from t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (order by a rows 3 preceding) from t', 'SELECT "sum"("a") OVER (ORDER BY "a" ASC ROWS BETWEEN 3 PRECEDING AND CURRENT ROW) FROM "public"."t"'],
            'select sum(a) over w from t window w as (partition by b) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over w from t window w as (partition by b)', 'SELECT "sum"("a") OVER "w" FROM "public"."t" WINDOW "w" AS (PARTITION BY "b")'],
            'select sum(a) over (w order by a) from t window w as (partition by b) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (w order by a) from t window w as (partition by b)', 'SELECT "sum"("a") OVER ("w" ORDER BY "a" ASC) FROM "public"."t" WINDOW "w" AS (PARTITION BY "b")'],
            'select sum(a) over (order by a rows between 1 preceding and 1 following) from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (order by a rows between 1 preceding and 1 following) from t', 'SELECT sum(`a`) OVER (ORDER BY `a` ASC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM `t`'],
            'select sum(a) over (w order by a) from t window w as (partition by b) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'select sum(a) over (w order by a) from t window w as (partition by b)', 'SELECT sum(`a`) OVER (`w` ORDER BY `a` ASC) FROM `t` WINDOW `w` AS (PARTITION BY `b`)'],
        ];
    }
}

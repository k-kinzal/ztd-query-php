<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\WindowBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WindowBinder::class)]
#[Medium]
final class WindowBinderTest extends TestCase
{
    public function testBindReadsNamedReferencesAndFullSpecifications(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER w, SUM(a) OVER (PARTITION BY b ORDER BY a), SUM(a) OVER (w ROWS 1 PRECEDING) FROM t WINDOW w AS (PARTITION BY b)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        [$named, $full, $based] = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $named);
        self::assertInstanceOf(\SqlSemantics\Model\Window\NamedWindow::class, $named->window);
        self::assertSame('w', $named->window->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $full);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $full->window);
        self::assertNull($full->window->base);
        self::assertSame('b', $full->window->partitionBy[0]->columnBinding()?->column->name);
        $orderKey = $full->window->orderBy[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $orderKey);
        self::assertSame('a', $orderKey->columnBinding()?->column->name);
        self::assertNull($full->window->frame);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $based);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $based->window);
        self::assertSame('w', $based->window->base);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Rows, $based->window->frame?->unit);
    }

    public function testFrameReadsUnitBoundariesAndExclusion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER (ORDER BY a GROUPS BETWEEN 1 PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE GROUP), SUM(a) OVER (ROWS CURRENT ROW) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $bounded = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $bounded);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $bounded->window);
        $frame = $bounded->window->frame;
        self::assertNotNull($frame);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Groups, $frame->unit);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Offset::class, $frame->start);
        self::assertSame(\SqlSemantics\Model\Window\Direction::Preceding, $frame->start->direction);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Unbounded::class, $frame->end);
        self::assertSame(\SqlSemantics\Model\Window\Direction::Following, $frame->end->direction);
        self::assertSame(\SqlSemantics\Model\Window\FrameExclusion::Group, $frame->exclusion);
        $single = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $single);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $single->window);
        $singleFrame = $single->window->frame;
        self::assertNotNull($singleFrame);
        self::assertInstanceOf(\SqlSemantics\Model\Window\CurrentRow::class, $singleFrame->start);
        self::assertInstanceOf(\SqlSemantics\Model\Window\CurrentRow::class, $singleFrame->end);
        self::assertSame(\SqlSemantics\Model\Window\FrameExclusion::None, $singleFrame->exclusion);
    }

    public function testBoundaryBindsOffsetExpressionsWithTheirDirection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER (PARTITION BY b ORDER BY a RANGE BETWEEN 1 PRECEDING AND 2 FOLLOWING EXCLUDE TIES) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $call);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $call->window);
        $frame = $call->window->frame;
        self::assertNotNull($frame);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Range, $frame->unit);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Offset::class, $frame->start);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $frame->start->value);
        self::assertSame('1', $frame->start->value->text);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Offset::class, $frame->end);
        self::assertSame(\SqlSemantics\Model\Window\Direction::Following, $frame->end->direction);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $frame->end->value);
        self::assertSame('2', $frame->end->value->text);
        self::assertSame(\SqlSemantics\Model\Window\FrameExclusion::Ties, $frame->exclusion);
        self::assertSame('SELECT sum("a") OVER (PARTITION BY "b" ORDER BY "a" ASC RANGE BETWEEN 1 PRECEDING AND 2 FOLLOWING EXCLUDE TIES) FROM "main"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsLowercaseFrames')]
    public function testBindReadsLowercaseFrames(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsLowercaseFrames(): iterable
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


    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, null, 'SELECT sum(a) OVER (ORDER BY a) FROM t', 'SELECT sum("a") OVER (ORDER BY "a" ASC) FROM "main"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, null, 'SELECT sum(a) OVER (ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM t', 'SELECT sum("a") OVER (ORDER BY "a" ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM "main"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, null, 'SELECT sum(a) OVER (w ROWS UNBOUNDED PRECEDING) FROM t WINDOW w AS (ORDER BY a)', 'SELECT sum("a") OVER ("w" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM "main"."t" WINDOW "w" AS (ORDER BY "a" ASC)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, null, 'SELECT sum(a) OVER (w ROWS UNBOUNDED PRECEDING) FROM t WINDOW w AS (ORDER BY a)', 'SELECT "sum"("a") OVER ("w" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM "public"."t" WINDOW "w" AS (ORDER BY "a" ASC)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'SELECT sum(b) OVER (ORDER BY a RANGE BETWEEN INTERVAL 1 DAY PRECEDING AND UNBOUNDED FOLLOWING) FROM t', 'SELECT sum(`b`) OVER (ORDER BY `a` ASC RANGE BETWEEN INTERVAL 1 DAY PRECEDING AND UNBOUNDED FOLLOWING) FROM `t`'])]
    public function testBindKeepsTheOrderingAndBothFrameBoundaries(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a DATE, b INT)'));
        $query = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }
}

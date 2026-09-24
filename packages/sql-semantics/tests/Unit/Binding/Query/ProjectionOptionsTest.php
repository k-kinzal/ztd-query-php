<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\ProjectionOptions;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProjectionOptions::class)]
#[Medium]
final class ProjectionOptionsTest extends TestCase
{
    public function testQuantifierDistinguishesAllDistinctAndDistinctOn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $all = $binder->bind('SELECT ALL a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $all);
        self::assertInstanceOf(\SqlSemantics\Model\Query\AllRows::class, $all->quantifier);
        $distinct = $binder->bind('SELECT DISTINCT a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $distinct);
        self::assertInstanceOf(\SqlSemantics\Model\Query\DistinctRows::class, $distinct->quantifier);
        $on = $binder->bind('SELECT DISTINCT ON (a, b) a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $on);
        self::assertInstanceOf(\SqlSemantics\Model\Query\DistinctOn::class, $on->quantifier);
        self::assertSame(['a', 'b'], array_map(static fn ($key): ?string => $key->columnBinding()?->column->name, $on->quantifier->keys));
        self::assertSame('SELECT DISTINCT ON("a", "b") "a" AS "a" FROM "public"."t"', $on->toString());
    }

    public function testWindowsBindsEachNamedWindowSpecification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER w FROM t WINDOW w AS (PARTITION BY b ORDER BY a ROWS BETWEEN 1 PRECEDING AND CURRENT ROW), v AS ()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame(['w', 'v'], array_column($statement->windows, 'name'));
        $specification = $statement->windows[0]->specification;
        self::assertNull($specification->base);
        self::assertSame('b', $specification->partitionBy[0]->columnBinding()?->column->name);
        $key = $specification->orderBy[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $key);
        self::assertSame('a', $key->columnBinding()?->column->name);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Rows, $specification->frame?->unit);
        self::assertNull($statement->windows[1]->specification->frame);
        self::assertSame([], $statement->windows[1]->specification->partitionBy);
    }

    public function testWindowsReadsMySqlDefinitionsThatReferenceAnotherWindow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER (w ORDER BY a) FROM t WINDOW w AS (PARTITION BY b)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('w', $statement->windows[0]->name);
        self::assertSame('b', $statement->windows[0]->specification->partitionBy[0]->columnBinding()?->column->name);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $call);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $call->window);
        self::assertSame('w', $call->window->base);
    }
}

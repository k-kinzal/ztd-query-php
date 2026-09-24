<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\CteBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CteBinder::class)]
#[Medium]
final class CteBinderTest extends TestCase
{
    public function testDefinitionKeepsAliasesAndMaterializationPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE c(n) AS MATERIALIZED (SELECT 1) SELECT n FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        $definition = $statement->ctes->definitions[0];
        self::assertSame('c', $definition->name);
        self::assertSame(['n'], $definition->columns);
        self::assertSame(\SqlSemantics\Model\Query\Materialization::Materialized, $definition->materialization);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $definition->query);
        self::assertSame('WITH RECURSIVE "c"("n") AS MATERIALIZED(SELECT 1) SELECT "n" AS "n" FROM "c"', $statement->toString());
    }

    public function testDefinitionReadsNotMaterializedAndTheDefaultPolicy(): void
    {
        $inline = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c AS NOT MATERIALIZED (SELECT 1) SELECT * FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $inline);
        self::assertNotNull($inline->ctes);
        self::assertSame(\SqlSemantics\Model\Query\Materialization::Inline, $inline->ctes->definitions[0]->materialization);
        $default = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $default);
        self::assertNotNull($default->ctes);
        self::assertSame(\SqlSemantics\Model\Query\Materialization::Default, $default->ctes->definitions[0]->materialization);
        self::assertSame([], $default->ctes->definitions[0]->columns);
    }

    public function testDefinitionRejectsMoreAliasesThanResultColumns(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::CteColumnCount->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c(x, y) AS (SELECT 1) SELECT * FROM c');
    }

    public function testDefinitionAllowsFewerAliasesOnlyForPostgreSql(): void
    {
        $partial = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c(x) AS (SELECT 1, 2) SELECT * FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $partial);
        self::assertSame(['x', '?column?'], array_column($partial->outputs, 'name'));
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('WITH c(x) AS (SELECT 1, 2) SELECT * FROM c');
    }

    public function testRecursiveIsReadFromTheWithClause(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $recursive = $binder->bind('WITH RECURSIVE c AS (SELECT 1) SELECT * FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $recursive);
        self::assertTrue($recursive->ctes?->recursive);
        $plain = $binder->bind('WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $plain);
        self::assertFalse($plain->ctes?->recursive);
        self::assertTrue(CteBinder::recursive($recursive->origin->source, null));
        self::assertFalse(CteBinder::recursive($plain->origin->source, null));
    }

    public function testClauseReturnsTheContextClauseOnlyWhenTheStatementOwnsOne(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $owned = $binder->bind('WITH c AS (SELECT 1) DELETE FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $owned);
        self::assertNotNull($owned->ctes);
        self::assertSame('c', $owned->ctes->definitions[0]->name);
        $nested = $binder->bind('DELETE FROM t WHERE a IN (WITH c AS (SELECT 1) SELECT * FROM c)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\DeleteTableStatement::class, $nested);
        self::assertNull($nested->ctes);
    }
}

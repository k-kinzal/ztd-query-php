<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Statistics\CreateExpressionStatisticsStatement;
use SqlSemantics\Model\Statement\Definition\Statistics\CreateStatisticsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateExpressionStatisticsStatement::class)]
#[Medium]
final class CreateExpressionStatisticsStatementTest extends TestCase
{
    public function testBindsTheExpressionAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)'));
        $statement = $binder->bind('CREATE STATISTICS s ON (a + 1) FROM t');
        self::assertInstanceOf(CreateExpressionStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS "s" ON (("a" + 1)) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON upper(b) FROM t');
        self::assertInstanceOf(CreateExpressionStatisticsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE STATISTICS ON ("upper"("b")) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON upper(b) FROM t');
        self::assertInstanceOf(CreateExpressionStatisticsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON upper(b) FROM t');
        self::assertInstanceOf(CreateExpressionStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS "app"."s" ON ("upper"("b")) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['app', 's']))));
        self::assertNull($statement->name);
    }

    public function testWithIfNotExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS s ON upper(b) FROM t');
        self::assertInstanceOf(CreateExpressionStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS IF NOT EXISTS "s" ON ("upper"("b")) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfNotExists(true)));
        self::assertFalse($statement->ifNotExists);
    }

    public function testWithExpressionRejectsAPlainColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)'));
        $statement = $binder->bind('CREATE STATISTICS s ON upper(b) FROM t');
        $columns = $binder->bind('CREATE STATISTICS s ON a, (a * 2) FROM t');
        self::assertInstanceOf(CreateExpressionStatisticsStatement::class, $statement);
        self::assertInstanceOf(CreateStatisticsStatement::class, $columns);
        self::assertSame('CREATE STATISTICS "s" ON (("a" * 2)) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withExpression($columns->elements[1])));
        $this->expectException(InvalidStructure::class);
        $statement->withExpression($columns->elements[0]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Statistics\CreateStatisticsStatement;
use SqlSemantics\Model\Statement\Definition\Statistics\StatisticsKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateStatisticsStatement::class)]
#[Medium]
final class CreateStatisticsStatementTest extends TestCase
{
    public function testBindsTheElementsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)'));
        $statement = $binder->bind('CREATE STATISTICS IF NOT EXISTS app.s (mcv, ndistinct) ON a, lower(b) FROM ONLY t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame([['app', 's'], true, [StatisticsKind::MostCommonValues, StatisticsKind::DistinctCounts]], [$statement->name?->parts, $statement->ifNotExists, $statement->kinds]);
        self::assertSame(['public', 't'], $statement->table->name->parts);
        self::assertSame('CREATE STATISTICS IF NOT EXISTS "app"."s"("mcv", "ndistinct") ON "a", ("lower"("b")) FROM ONLY "public"."t"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsARepeatedKind(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withKinds([StatisticsKind::Dependencies, StatisticsKind::Dependencies]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE STATISTICS ON "a", "b" FROM "public"."t"', $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS "s" ON "a", "b" FROM "public"."t"', $statement->withName(new QualifiedName(['s']))->toString());
        self::assertNull($statement->name);
    }

    public function testWithIfNotExistsRequiresAName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS s ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS IF NOT EXISTS "s" ON "a", "b" FROM "public"."t"', $statement->withIfNotExists(true)->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withName(null)->withIfNotExists(true);
    }

    public function testWithKindsReplacesTheKinds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS s (mcv) ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS "s" ON "a", "b" FROM "public"."t"', $statement->withKinds([])->toString());
        self::assertSame([StatisticsKind::MostCommonValues], $statement->kinds);
    }

    public function testWithElementsReplacesTheElements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)')))->bind('CREATE STATISTICS s ON a, b FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame('CREATE STATISTICS "s" ON "b", "a" FROM "public"."t"', $statement->withElements([$statement->elements[1], $statement->elements[0]])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withElements([$statement->elements[0]]);
    }
}

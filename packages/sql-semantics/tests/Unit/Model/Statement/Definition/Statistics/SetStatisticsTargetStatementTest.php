<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Statistics\SetStatisticsTargetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetStatisticsTargetStatement::class)]
#[Medium]
final class SetStatisticsTargetStatementTest extends TestCase
{
    public function testBindsTheTargetAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER STATISTICS IF EXISTS app.s SET STATISTICS DEFAULT');
        self::assertInstanceOf(SetStatisticsTargetStatement::class, $statement);
        self::assertSame([['app', 's'], true, -1], [$statement->name->parts, $statement->ifExists, $statement->target]);
        self::assertSame('ALTER STATISTICS IF EXISTS "app"."s" SET STATISTICS -1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS 5');
        self::assertInstanceOf(SetStatisticsTargetStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER STATISTICS "s" SET STATISTICS 5', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS 5');
        self::assertInstanceOf(SetStatisticsTargetStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS 5');
        self::assertInstanceOf(SetStatisticsTargetStatement::class, $statement);
        self::assertSame('ALTER STATISTICS "app"."s2" SET STATISTICS 5', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['app', 's2']))));
        self::assertSame(['s'], $statement->name->parts);
    }

    public function testWithIfExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS 5');
        self::assertInstanceOf(SetStatisticsTargetStatement::class, $statement);
        self::assertSame('ALTER STATISTICS IF EXISTS "s" SET STATISTICS 5', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfExists(true)));
        self::assertFalse($statement->ifExists);
    }

    public function testWithTargetRejectsATargetOutsideTheRange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS 5');
        self::assertInstanceOf(SetStatisticsTargetStatement::class, $statement);
        self::assertSame('ALTER STATISTICS "s" SET STATISTICS 10000', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTarget(10000)));
        $this->expectException(InvalidStructure::class);
        $statement->withTarget(10001);
    }
}

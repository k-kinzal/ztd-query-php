<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\MoveTablespaceRelationsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MoveTablespaceRelationsStatement::class)]
#[Medium]
final class MoveTablespaceRelationsStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        self::assertSame(Kind\RelationKind::Index, $statement->relationKind);
        self::assertSame('slow', $statement->tablespace);
        self::assertSame('fast', $statement->newTablespace);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser], $statement->owners);
        self::assertTrue($statement->nowait);
        self::assertSame('ALTER INDEX ALL IN TABLESPACE "slow" OWNED BY "alice", CURRENT_USER SET TABLESPACE "fast" NOWAIT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithRelationKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $changed = $statement->withRelationKind(Kind\RelationKind::MaterializedView);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\RelationKind::Index, $statement->relationKind);
        self::assertEquals(Kind\RelationKind::MaterializedView, $changed->relationKind);
        self::assertStringContainsString('ALTER MATERIALIZED VIEW ALL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTablespaceReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $changed = $statement->withTablespace('cold');
        self::assertNotSame($statement, $changed);
        self::assertEquals('slow', $statement->tablespace);
        self::assertEquals('cold', $changed->tablespace);
        self::assertStringContainsString('TABLESPACE "cold"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNewTablespaceReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $changed = $statement->withNewTablespace('hot');
        self::assertNotSame($statement, $changed);
        self::assertEquals('fast', $statement->newTablespace);
        self::assertEquals('hot', $changed->newTablespace);
        self::assertStringContainsString('SET TABLESPACE "hot"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOwnersReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $changed = $statement->withOwners([]);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser], $statement->owners);
        self::assertEquals([], $changed->owners);
        self::assertStringContainsString('"slow" SET TABLESPACE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNowaitReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER INDEX ALL IN TABLESPACE slow OWNED BY alice, CURRENT_USER SET TABLESPACE fast NOWAIT', strict: false);
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $changed = $statement->withNowait(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->nowait);
        self::assertEquals(false, $changed->nowait);
        self::assertStringContainsString('"fast"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsASequence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLE ALL IN TABLESPACE a SET TABLESPACE b');
        self::assertInstanceOf(MoveTablespaceRelationsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRelationKind(Kind\RelationKind::Sequence);
    }
}

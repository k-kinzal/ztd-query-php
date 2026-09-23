<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TableDropScope;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\DropTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTableStatement::class)]
#[Medium]
final class DropTableStatementTest extends TestCase
{
    public function testWithNamesKeepsTheTemporaryNamespaceSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TEMPORARY TABLE IF EXISTS t');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $changed = $statement->withNames([new QualifiedName(['app', 'u']), new QualifiedName(['v'])]);
        self::assertSame('DROP TEMPORARY TABLE IF EXISTS `app`.`u`, `v`', $changed->toString());
        self::assertSame(['t'], $statement->names[0]->parts);
        self::assertSame(TableDropScope::Temporary, $changed->selection);
    }

    public function testWithIfExistsChangesOnlyTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TEMPORARY TABLE t');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertTrue($changed->ifExists);
        self::assertSame('DROP TEMPORARY TABLE IF EXISTS `t`', $changed->toString());
    }

    public function testWithBehaviorChangesTheDependencyPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TABLE app.t RESTRICT');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Cascade);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
        self::assertSame(DropBehavior::Cascade, $changed->behavior);
        self::assertSame('DROP TABLE "app"."t" CASCADE', $changed->toString());
    }

    public function testWithSelectionChangesTheTableNamespace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLE t');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $changed = $statement->withSelection(TableDropScope::Temporary);
        self::assertSame(TableDropScope::Visible, $statement->selection);
        self::assertSame('DROP TEMPORARY TABLE `t`', $changed->toString());
    }

    public function testWithOriginRetainsTheDeletionRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TEMPORARY TABLES IF EXISTS t,u');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->names, $copy->names);
        self::assertSame($statement->selection, $copy->selection);
    }

    public function testWithSelectionRejectsTemporaryOnlyDeletionOutsideMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DROP TABLE t');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withSelection(TableDropScope::Temporary);
    }

    public function testWithNamesRejectsMultipleSQLiteTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DROP TABLE t');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withNames([new QualifiedName(['a']), new QualifiedName(['b'])]);
    }

    public function testWithBehaviorRejectsSQLiteDependencyPolicies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DROP TABLE t');
        self::assertInstanceOf(DropTableStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withBehavior(DropBehavior::Cascade);
    }

}

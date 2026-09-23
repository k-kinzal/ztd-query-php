<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TableDropScope;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Statement\Definition\CreateTableAsStatement;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;
use SqlSemantics\Model\Statement\Definition\DropIndexStatement;
use SqlSemantics\Model\Statement\Definition\DropTableStatement;
use SqlSemantics\Model\Statement\Definition\DropTriggerStatement;
use SqlSemantics\Model\Statement\Definition\DropViewStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\CreateMaterializedViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\ObjectBinder::class)]
#[Medium]
final class ObjectBinderTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE VIRTUAL TABLE v USING fts5(a)', CreateVirtualTableStatement::class])]
    #[TestWith(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END', CreateSqliteTriggerStatement::class])]
    #[TestWith(['CREATE VIEW v AS SELECT a FROM t', CreateViewStatement::class])]
    #[TestWith(['CREATE TABLE u AS SELECT a FROM t', CreateTableAsStatement::class])]
    #[TestWith(['DROP TABLE IF EXISTS t', DropTableStatement::class])]
    public function testBindDispatchesByTheOuterSchemaOperation(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)')))->bind($sql);
        self::assertInstanceOf($class, $statement);
    }

    public function testBindPrefersMaterializedViewsOverPlainViews(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
    }

    public function testDropReadsScopeExistenceAndDependencyPolicy(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $tables = $binder->bind('DROP TEMPORARY TABLE IF EXISTS a, b CASCADE');
        self::assertInstanceOf(DropTableStatement::class, $tables);
        self::assertSame(TableDropScope::Temporary, $tables->selection);
        self::assertTrue($tables->ifExists);
        self::assertSame(DropBehavior::Cascade, $tables->behavior);
        self::assertInstanceOf(DropViewStatement::class, $binder->bind('DROP VIEW v'));
        self::assertInstanceOf(DropIndexStatement::class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX i'));
        self::assertInstanceOf(DropTriggerStatement::class, (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DROP TRIGGER IF EXISTS tr'));
    }

    public function testTableAsReadsDeclaredNamesStorageAndPopulation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE UNLOGGED TABLE IF NOT EXISTS u (x) WITH (fillfactor = 70) AS SELECT a FROM t WITH NO DATA');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame(['x'], $statement->columns);
        self::assertTrue($statement->ifNotExists);
        self::assertFalse($statement->withData);
    }

    public function testNameReadsTheQualifiedDeclarationTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)')))->bind('CREATE TABLE main.u AS SELECT a FROM t');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame(['main', 'u'], $statement->name->parts);
    }

}

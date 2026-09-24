<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\ColumnActions;

#[CoversClass(ColumnActions::class)]
#[Medium]
final class ColumnActionsTest extends TestCase
{
    public function testWriteWritesEachColumnChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ALTER id SET STORAGE main', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('ALTER COLUMN "id" SET STORAGE MAIN', ColumnActions::write($statement->actions[0])?->toString());
    }

    public function testWriteReturnsNullForRelationLevelActions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t SET LOGGED', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertNull(ColumnActions::write($statement->actions[0]));
    }

    public function testAlterPrefixesTheColumn(): void
    {
        self::assertSame('ALTER COLUMN "c" DROP DEFAULT', ColumnActions::alter(\SqlSemantics\Model\Sql\Build::identifier(['c'], Dialect::PostgreSql), [\SqlSemantics\Model\Sql\Build::keyword('DROP DEFAULT')])->toString());
    }

    public function testOptionsWritesStorageOptionsAndIdentityChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ALTER id RESET (n_distinct)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('ALTER COLUMN "id" RESET("n_distinct")', ColumnActions::options($statement->actions[0])?->toString());
    }

    public function testAddPlacesWrapperOptionsBeforeConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER FOREIGN TABLE t ADD c integer OPTIONS (a 'b') NOT NULL");
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\AddColumn::class, $statement->actions[0]);
        self::assertSame('ADD COLUMN "c" integer OPTIONS("a" \'b\') NOT NULL', ColumnActions::add($statement->actions[0])->toString());
    }

    public function testTypeWritesCollationAndConversion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id TYPE text COLLATE "C" USING id::text');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\ColumnTypeChange::class, $statement->actions[0]);
        self::assertSame('TYPE text COLLATE "C" USING CAST("id" AS text)', implode(' ', array_map(static fn ($tree): string => $tree->toString(), ColumnActions::type($statement->actions[0]))));
    }
}

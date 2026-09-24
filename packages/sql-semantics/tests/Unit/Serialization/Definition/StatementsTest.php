<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Statements;

#[CoversClass(Statements::class)]
#[Medium]
final class StatementsTest extends TestCase
{
    #[TestWith(['DROP TABLE t', 'DROP TABLE "t"', Dialect::PostgreSql])]
    #[TestWith(['DROP FUNCTION f()', 'DROP FUNCTION "f"()', Dialect::PostgreSql])]
    #[TestWith(['ALTER TABLE t RENAME TO renamed', 'ALTER TABLE "t" RENAME TO "renamed"', Dialect::Sqlite])]
    #[TestWith(['CREATE FOREIGN DATA WRAPPER fdw', 'CREATE FOREIGN DATA WRAPPER "fdw" NO HANDLER NO VALIDATOR', Dialect::PostgreSql])]
    #[TestWith(['IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app', 'IMPORT FOREIGN SCHEMA "ext" FROM SERVER "remote" INTO "app"', Dialect::PostgreSql])]
    public function testWriteRoutesEachDefinitionFamily(string $sql, string $expected, Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind($sql);
        $tree = Statements::write($statement);
        self::assertNotNull($tree);
        self::assertSame($expected, $tree->toString());
    }

    #[TestWith(['SELECT 1'])]
    #[TestWith(['INSERT INTO t VALUES (1)'])]
    #[TestWith(['COMMIT'])]
    public function testWriteLeavesOtherOperationsForTheirOwnSerializer(string $sql): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        self::assertNull(Statements::write((new Binder($schema))->bind($sql)));
    }

    #[TestWith(['DROP TABLE t', 'DROP TABLE "t"'])]
    #[TestWith(['CREATE INDEX ix ON t (id)', 'CREATE INDEX "ix" ON "public"."t"("id")'])]
    #[TestWith(['COMMENT ON TABLE t IS NULL', null])]
    public function testSchemaCommandsWritesTheGenericTableAndIndexCommands(string $sql, ?string $expected): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        self::assertSame($expected, Statements::schemaCommands((new Binder($schema))->bind($sql))?->toString());
    }

    #[TestWith(['ALTER EVENT TRIGGER e DISABLE', 'ALTER EVENT TRIGGER "e" DISABLE', Dialect::PostgreSql])]
    #[TestWith(['DROP INDEX CONCURRENTLY ix', 'DROP INDEX CONCURRENTLY "ix"', Dialect::PostgreSql])]
    #[TestWith(['DROP TRIGGER tr ON t', 'DROP TRIGGER "tr" ON "t"', Dialect::PostgreSql])]
    #[TestWith(['CREATE TABLE q AS SELECT 1', 'CREATE TABLE "q" AS SELECT 1', Dialect::PostgreSql])]
    #[TestWith(['DROP INDEX ix ON t', 'DROP INDEX `ix` ON `t` ALGORITHM = DEFAULT LOCK = DEFAULT', Dialect::MySql])]
    #[TestWith(['CREATE TABLE z LIKE t', 'CREATE TABLE `z` LIKE `t`', Dialect::MySql])]
    #[TestWith(['DROP VIEW v', 'DROP VIEW "v"', Dialect::Sqlite])]
    #[TestWith(['DROP INDEX ix', 'DROP INDEX "ix"', Dialect::Sqlite])]
    #[TestWith(['DROP TRIGGER tr', 'DROP TRIGGER "tr"', Dialect::Sqlite])]
    #[TestWith(['ALTER TABLE t RENAME COLUMN b TO c', 'ALTER TABLE "t" RENAME COLUMN "b" TO "c"', Dialect::Sqlite])]
    #[TestWith(['ALTER TABLE t DROP COLUMN b', 'ALTER TABLE "t" DROP COLUMN "b"', Dialect::Sqlite])]
    #[TestWith(['ALTER TABLE t ADD COLUMN c INT', 'ALTER TABLE "t" ADD COLUMN "c" "int"', Dialect::Sqlite])]
    #[TestWith(['CREATE VIRTUAL TABLE v USING fts5(a)', 'CREATE VIRTUAL TABLE "v" USING "fts5"(a)', Dialect::Sqlite])]
    #[TestWith(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END', 'CREATE TRIGGER "tr" AFTER INSERT ON "main"."t" FOR EACH ROW BEGIN SELECT 1; END', Dialect::Sqlite])]
    #[TestWith(['CREATE TABLE y (a INT)', 'CREATE TABLE "main"."y"("a" "int")', Dialect::Sqlite])]
    #[TestWith(['CREATE INDEX ix ON t(id)', 'CREATE INDEX "main"."ix" ON "t"("id")', Dialect::Sqlite])]
    public function testWriteRoutesEveryGenericSchemaCommand(string $sql, string $expected, Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER, b INT)');
        self::assertSame($expected, Statements::write((new Binder($schema))->bind($sql))?->toString());
    }
}

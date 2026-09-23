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
}

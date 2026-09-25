<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\RelationCommands;

#[CoversClass(RelationCommands::class)]
#[Medium]
final class RelationCommandsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(RelationCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['ALTER TABLE ONLY t SET LOGGED'])]
    #[TestWith(['ALTER VIEW v RENAME COLUMN a TO b'])]
    #[TestWith(['ALTER TABLE t RENAME CONSTRAINT a TO b'])]
    #[TestWith(['ALTER SEQUENCE IF EXISTS s SET SCHEMA x'])]
    #[TestWith(['ALTER INDEX ALL IN TABLESPACE a SET TABLESPACE b NOWAIT'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), RelationCommands::write($statement)?->toString());
    }

    #[TestWith([Kind\RelationKind::ForeignTable, true, true, 'ALTER FOREIGN TABLE IF EXISTS ONLY "f"'])]
    #[TestWith([Kind\RelationKind::MaterializedView, false, false, 'ALTER MATERIALIZED VIEW "f"'])]
    public function testTargetWritesTheHeadOfARelationCommand(Kind\RelationKind $kind, bool $ifExists, bool $only, string $expected): void
    {
        self::assertSame($expected, RelationCommands::target($kind, new QualifiedName(['f']), $ifExists, $only)->toString());
    }

    #[TestWith(['ALTER TABLE t RENAME TO u', 'ALTER TABLE "t" RENAME TO "u"'])]
    #[TestWith(['ALTER TABLE ONLY t SET LOGGED', 'ALTER TABLE ONLY "t" SET LOGGED'])]
    #[TestWith(['ALTER VIEW v RENAME COLUMN a TO b', 'ALTER VIEW "v" RENAME COLUMN "a" TO "b"'])]
    #[TestWith(['ALTER TABLE t RENAME CONSTRAINT a TO b', 'ALTER TABLE "t" RENAME CONSTRAINT "a" TO "b"'])]
    #[TestWith(['ALTER SEQUENCE IF EXISTS s SET SCHEMA x', 'ALTER SEQUENCE IF EXISTS "s" SET SCHEMA "x"'])]
    #[TestWith(['ALTER INDEX ALL IN TABLESPACE a SET TABLESPACE b NOWAIT', 'ALTER INDEX ALL IN TABLESPACE "a" SET TABLESPACE "b" NOWAIT'])]
    #[TestWith(['ALTER TABLE ALL IN TABLESPACE a OWNED BY r1, r2 SET TABLESPACE b', 'ALTER TABLE ALL IN TABLESPACE "a" OWNED BY "r1", "r2" SET TABLESPACE "b"'])]
    #[TestWith(['ALTER TABLE t ADD COLUMN c INT, DROP COLUMN n', 'ALTER TABLE "t" ADD COLUMN "c" integer, DROP COLUMN "n"'])]
    public function testWriteSpellsEachRelationCommand(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($expected, RelationCommands::write($statement)?->toString());
    }
}

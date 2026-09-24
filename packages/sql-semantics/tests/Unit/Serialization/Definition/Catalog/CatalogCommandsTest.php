<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Catalog\CatalogCommands;

#[CoversClass(CatalogCommands::class)]
#[Medium]
final class CatalogCommandsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(CatalogCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['COMMENT ON TABLE t IS \'x\''])]
    #[TestWith(['SECURITY LABEL FOR p ON ROLE r IS NULL'])]
    #[TestWith(['ALTER SCHEMA s OWNER TO CURRENT_USER'])]
    #[TestWith(['ALTER FUNCTION f() SET SCHEMA s'])]
    #[TestWith(['ALTER TRIGGER a ON t RENAME TO b'])]
    #[TestWith(['ALTER DOMAIN d RENAME CONSTRAINT a TO b'])]
    #[TestWith(['ALTER TYPE ty RENAME ATTRIBUTE a TO b'])]
    #[TestWith(['ALTER POLICY p ON t RENAME TO q'])]
    #[TestWith(['ALTER INDEX ix DEPENDS ON EXTENSION e'])]
    #[TestWith(['ALTER INDEX ix NO DEPENDS ON EXTENSION e'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($statement->toString(), CatalogCommands::write($statement)?->toString());
    }

    public function testTextWritesNullOrTheOriginalSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON SCHEMA s IS E'a''b'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, $statement);
        self::assertSame("E'a''b'", CatalogCommands::text($statement->comment)->toString());
        self::assertSame('NULL', CatalogCommands::text(null)->toString());
    }

    #[TestWith(["COMMENT ON TABLE t IS 'x'", 'COMMENT ON TABLE "t" IS \'x\''])]
    #[TestWith(['SECURITY LABEL FOR p ON ROLE r IS NULL', 'SECURITY LABEL FOR "p" ON ROLE "r" IS NULL'])]
    #[TestWith(["SECURITY LABEL ON ROLE r IS 'l'", 'SECURITY LABEL ON ROLE "r" IS \'l\''])]
    #[TestWith(['ALTER SCHEMA s OWNER TO CURRENT_USER', 'ALTER SCHEMA "s" OWNER TO CURRENT_USER'])]
    #[TestWith(['ALTER FUNCTION f() SET SCHEMA s', 'ALTER FUNCTION "f"() SET SCHEMA "s"'])]
    #[TestWith(['ALTER TRIGGER a ON t RENAME TO b', 'ALTER TRIGGER "a" ON "t" RENAME TO "b"'])]
    #[TestWith(['ALTER DOMAIN d RENAME CONSTRAINT a TO b', 'ALTER DOMAIN "d" RENAME CONSTRAINT "a" TO "b"'])]
    #[TestWith(['ALTER TYPE ty RENAME ATTRIBUTE a TO b CASCADE', 'ALTER TYPE "ty" RENAME ATTRIBUTE "a" TO "b" CASCADE'])]
    #[TestWith(['ALTER POLICY p ON t RENAME TO q', 'ALTER POLICY "p" ON "t" RENAME TO "q"'])]
    #[TestWith(['ALTER POLICY IF EXISTS p ON t RENAME TO q', 'ALTER POLICY IF EXISTS "p" ON "t" RENAME TO "q"'])]
    #[TestWith(['ALTER INDEX ix DEPENDS ON EXTENSION e', 'ALTER INDEX "ix" DEPENDS ON EXTENSION "e"'])]
    #[TestWith(['ALTER INDEX ix NO DEPENDS ON EXTENSION e', 'ALTER INDEX "ix" NO DEPENDS ON EXTENSION "e"'])]
    public function testWriteSpellsEveryPart(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($expected, CatalogCommands::write($statement)?->toString());
    }
}

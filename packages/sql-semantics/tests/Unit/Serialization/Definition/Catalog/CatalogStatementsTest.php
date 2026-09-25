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
use SqlSemantics\Serialization\Definition\Catalog\CatalogStatements;

#[CoversClass(CatalogStatements::class)]
#[Medium]
final class CatalogStatementsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(CatalogStatements::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['COMMENT ON TABLE t IS NULL'])]
    #[TestWith(['DROP SCHEMA s'])]
    #[TestWith(['ALTER TABLE t SET LOGGED'])]
    #[TestWith(['ALTER TABLE t RENAME TO u'])]
    #[TestWith(['ALTER TABLE t SET SCHEMA s'])]
    #[TestWith(['ALTER TABLE ALL IN TABLESPACE a SET TABLESPACE b'])]
    #[TestWith(['CREATE FOREIGN TABLE f () SERVER s'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), CatalogStatements::write($statement)?->toString());
    }
}

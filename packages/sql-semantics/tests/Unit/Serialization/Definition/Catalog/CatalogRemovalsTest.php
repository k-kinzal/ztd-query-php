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
use SqlSemantics\Serialization\Definition\Catalog\CatalogRemovals;

#[CoversClass(CatalogRemovals::class)]
#[Medium]
final class CatalogRemovalsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(CatalogRemovals::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['DROP SEQUENCE IF EXISTS s CASCADE'])]
    #[TestWith(['DROP COLLATION c'])]
    #[TestWith(['DROP EXTENSION a, b RESTRICT'])]
    #[TestWith(['DROP RULE r ON t'])]
    #[TestWith(['DROP TYPE integer[]'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($statement->toString(), CatalogRemovals::write($statement)?->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\CreatePublication::class)]
#[Medium]
final class CreatePublicationTest extends TestCase
{
    public function testRenderWritesAllTables(): void
    {
        self::assertSame('CREATE PUBLICATION p FOR ALL TABLES WITH (publish = \'insert\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR ALL TABLES WITH (publish = \'insert\')')->toString());
    }

    public function testRenderWithoutObjects(): void
    {
        self::assertSame('CREATE PUBLICATION p', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p')->toString());
    }

    public function testDeriveStatementReportsAListWithoutKeywords(): void
    {
        self::assertSame('invalid publication object list', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR t')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsCurrentSchemaAfterATable(): void
    {
        self::assertSame('invalid table name', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLE t, CURRENT_SCHEMA')->facts->diagnostics[0]->message());
    }

    public function testRejectsObjectsWithAllTables(): void
    {
        $this->expectExceptionMessage('FOR ALL TABLES has no object list.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\CreatePublication(new \SqlSemantics\Statement\Identifier\Name('p'), true, [new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema(null)]);
    }

    public function testRejectsASchemaNameThatWouldReadAsATable(): void
    {
        $this->expectExceptionMessage('A schema name without TABLES IN SCHEMA continues a schema item.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\CreatePublication(new \SqlSemantics\Statement\Identifier\Name('p'), false, [new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema(new \SqlSemantics\Statement\Identifier\Name('s'), false)]);
    }
}

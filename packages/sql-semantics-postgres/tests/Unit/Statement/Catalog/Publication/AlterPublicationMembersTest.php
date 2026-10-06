<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\AlterPublicationMembers::class)]
#[Medium]
final class AlterPublicationMembersTest extends TestCase
{
    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER PUBLICATION p ADD TABLE t, TABLES IN SCHEMA s', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p ADD TABLE t, TABLES IN SCHEMA s')->toString());
    }

    public function testDeriveStatementReportsAFilterOnDrop(): void
    {
        self::assertSame('cannot use a WHERE clause when removing a table from a publication', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p DROP TABLE t WHERE (true)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsColumnsOnDrop(): void
    {
        self::assertSame('column list must not be specified in ALTER PUBLICATION ... DROP', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p DROP TABLE t (a)')->facts->diagnostics[0]->message());
    }

    public function testRejectsABareTableAfterASchema(): void
    {
        $this->expectExceptionMessage('A bare table name continues a table item.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\AlterPublicationMembers(new \SqlSemantics\Statement\Identifier\Name('p'), \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationAction::Add, [new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema(new \SqlSemantics\Statement\Identifier\Name('s')), new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), [], null, false)]);
    }
}

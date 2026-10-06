<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublicationList::class)]
#[Medium]
final class PublicationListTest extends TestCase
{
    public function testCheckAcceptsACurrentSchemaAnywhere(): void
    {
        self::assertSame('CREATE PUBLICATION p FOR TABLE t, CURRENT_SCHEMA', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLE t, CURRENT_SCHEMA')->toString());
    }

    public function testDeriveReportsAQualifiedTableAfterASchema(): void
    {
        self::assertSame('invalid schema name', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA s, a.b')->facts->diagnostics[0]->message());
    }

    public function testDeriveReportsAFilterAfterASchema(): void
    {
        self::assertSame('WHERE clause not allowed for schema', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA s, t WHERE (true)')->facts->diagnostics[0]->message());
    }

    public function testDeriveReportsColumnsAfterASchema(): void
    {
        self::assertSame('column specification not allowed for schema', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA s, t (a)')->facts->diagnostics[0]->message());
    }

    public function testRuleOfAnIntroducedItemIsNull(): void
    {
        self::assertNull((new \SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublicationList())->rule(null, new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema(null), null));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedViewLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateMaterializedViewLowering::class)]
#[Medium]
final class CreateMaterializedViewLoweringTest extends TestCase
{
    public function testLowerUsesTheCreationNamespaceAndKeepsTheExplicitClauses(): void
    {
        $result = (new Semantics())->analyze('CREATE MATERIALIZED VIEW IF NOT EXISTS report (n) USING heap AS VALUES (6) WITH NO DATA');
        self::assertInstanceOf(CreateMaterializedView::class, $result);
        self::assertSame('public', $result->name->schema->value);
        self::assertSame(['n'], array_column($result->columns, 'value'));
        self::assertTrue($result->ifNotExists);
        self::assertFalse($result->withData);
    }
}

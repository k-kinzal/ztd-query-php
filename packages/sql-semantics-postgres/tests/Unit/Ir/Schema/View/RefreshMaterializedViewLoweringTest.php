<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\RefreshMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\RefreshMaterializedViewLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RefreshMaterializedViewLowering::class)]
#[Medium]
final class RefreshMaterializedViewLoweringTest extends TestCase
{
    public function testLowerResolvesTheMaterializedRelation(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE MATERIALIZED VIEW report AS SELECT 6 AS n']);
        $ir = $semantics->analyze('REFRESH MATERIALIZED VIEW report WITH NO DATA', $context);

        self::assertInstanceOf(RefreshMaterializedView::class, $ir);
        self::assertSame('REFRESH MATERIALIZED VIEW "public"."report" WITH NO DATA', $ir->toString());
    }
}

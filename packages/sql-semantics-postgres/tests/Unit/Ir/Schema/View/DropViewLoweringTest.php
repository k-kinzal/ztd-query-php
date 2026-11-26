<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewRemovalKind;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(DropViewLowering::class)]
#[Medium]
final class DropViewLoweringTest extends TestCase
{
    public function testLowerResolvesTheMaterializedViewByItsDeclaredAbsoluteName(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE MATERIALIZED VIEW "daily Report" AS SELECT 42 AS n']);
        $drop = $semantics->analyze('DROP MATERIALIZED VIEW "daily Report"', $context);

        self::assertInstanceOf(DropView::class, $drop);
        self::assertSame(ViewRemovalKind::Materialized, $drop->kind);
        self::assertSame('DROP MATERIALIZED VIEW "public"."daily Report" RESTRICT', $drop->toString());
    }
}

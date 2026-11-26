<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ReindexTableLowering::class)]
#[Medium]
final class ReindexTableLoweringTest extends TestCase
{
    public function testLowerKeepsTheResolvedOwnerAndKind(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE MATERIALIZED VIEW public.selected AS SELECT 1 AS id',
            'CREATE TEMP TABLE selected (id integer)',
        ]);
        $temporary = $semantics->analyze('REINDEX TABLE selected', $context);
        $persistent = $semantics->analyze('REINDEX (CONCURRENTLY FALSE) TABLE CONCURRENTLY public.selected', $context);

        self::assertInstanceOf(ReindexTable::class, $temporary);
        self::assertInstanceOf(ReindexTable::class, $persistent);
        self::assertSame(ReindexTableKind::BaseTable, $temporary->target->kind);
        self::assertSame(ReindexTableKind::MaterializedView, $persistent->target->kind);
        self::assertSame('REINDEX TABLE "pg_temp"."selected"', $temporary->toString());
        self::assertSame('REINDEX TABLE CONCURRENTLY "public"."selected"', $persistent->toString());
    }
}

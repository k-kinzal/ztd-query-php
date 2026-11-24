<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ExclusionIndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndexLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ReindexIndexLowering::class)]
#[Medium]
final class ReindexIndexLoweringTest extends TestCase
{
    public function testLowerBindsTheIndexBeforeTheDecoyAndFoldsTheMode(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE public.orders (id integer)',
            'CREATE INDEX selected ON public.orders (id)',
            'CREATE TEMP TABLE orders (id integer)',
            'CREATE INDEX selected ON pg_temp.orders (id)',
        ]);
        $result = $semantics->analyze('REINDEX (CONCURRENTLY TRUE, CONCURRENTLY FALSE) INDEX selected', $context);

        self::assertInstanceOf(ReindexIndex::class, $result);
        self::assertSame('pg_temp', $result->target->schema->value);
        self::assertSame('orders', $result->target->table->value);
        self::assertSame(IndexRebuildMode::Standard, $result->mode);
        self::assertSame('REINDEX INDEX "pg_temp"."selected"', $result->toString());
        self::assertEquals($result, $semantics->analyze($result->toString(), $context));
    }

    public function testLowerSelectsTheExclusionIndexOverAnOrdinaryNamesake(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE public.orders (id integer)',
            'CREATE INDEX checked ON public.orders (id)',
            'CREATE TEMP TABLE orders (id integer, ready boolean, CONSTRAINT checked EXCLUDE USING btree (id WITH =) WHERE (ready))',
        ]);
        $result = $semantics->analyze('REINDEX INDEX CONCURRENTLY checked', $context);

        self::assertInstanceOf(ReindexIndex::class, $result);
        self::assertInstanceOf(ExclusionIndexReference::class, $result->target);
        self::assertSame('orders', $result->target->table->value);
        self::assertSame('REINDEX INDEX CONCURRENTLY "pg_temp"."checked"', $result->toString());
    }
}

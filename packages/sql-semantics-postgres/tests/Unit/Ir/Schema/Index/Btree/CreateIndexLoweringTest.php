<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateIndexLowering::class)]
#[Medium]
final class CreateIndexLoweringTest extends TestCase
{
    public function testLowerUsesTheResolvedNamespaceAndChecksGuardedOccupancy(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE public.orders (id bigint)',
            'CREATE TEMP TABLE orders (id integer)',
            'CREATE TABLE pg_temp.idx (payload integer)',
        ]);
        $index = $semantics->analyze('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx ON orders (id DESC)', $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertSame(IndexPresence::Occupied, $index->presence);
        self::assertSame(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS "idx" ON "pg_temp"."orders" USING btree ("id" DESC NULLS FIRST)',
            $index->toString(),
        );
    }
}

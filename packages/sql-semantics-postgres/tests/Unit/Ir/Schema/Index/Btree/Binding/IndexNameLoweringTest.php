<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexNameLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(IndexNameLowering::class)]
#[Medium]
final class IndexNameLoweringTest extends TestCase
{
    public function testLowerResolvesTheSameValueAsAnExplicitResolvedName(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE public.orders (id integer, payload integer)',
            'CREATE TABLE public.orders_id_payload_idx (decoy integer)',
            'CREATE TABLE alternate.orders_id_payload_idx1 (decoy integer)',
        ]);
        $value = $semantics->analyze('CREATE INDEX ON public.orders (id) INCLUDE (payload)', $context);
        $explicit = $semantics->analyze('CREATE INDEX orders_id_payload_idx1 ON public.orders (id) INCLUDE (payload)', $context);

        self::assertInstanceOf(CreateIndex::class, $value);
        self::assertSame(IndexPresence::Vacant, $value->presence);
        self::assertSame('orders_id_payload_idx1', $value->name->value);
        self::assertEquals($explicit, $value);
    }

    public function testLowerRecordsEachNameAllocatedEarlierInTheSchema(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('CREATE SCHEMA archive CREATE TABLE orders (id integer)'
            . ' CREATE SEQUENCE orders_id_idx CREATE INDEX ON orders (id)'
            . ' CREATE INDEX ON orders (id DESC)');

        self::assertInstanceOf(CreateSchema::class, $value);
        self::assertSame('orders_id_idx1', $value->indexes[0]->name->value);
        self::assertSame('orders_id_idx2', $value->indexes[1]->name->value);
    }
}

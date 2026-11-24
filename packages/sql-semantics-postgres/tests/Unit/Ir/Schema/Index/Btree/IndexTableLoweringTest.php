<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTableLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(IndexTableLowering::class)]
#[Medium]
final class IndexTableLoweringTest extends TestCase
{
    public function testLowerKeepsNativeColumnKindsInPhysicalOrder(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context(['CREATE TABLE public.orders (id integer, quantity bigint, ready boolean)']);
        $index = $analyzer->analyze('CREATE INDEX idx ON ONLY public.orders (ready) INCLUDE (quantity)', $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertSame([ColumnKind::Int4, ColumnKind::Int8, ColumnKind::Boolean], $index->table->columnKinds);
        self::assertSame(2, $index->keys[0]->slot);
        self::assertSame(1, $index->included[0]->slot);
        self::assertTrue($index->table->only);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPredicateLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(IndexPredicateLowering::class)]
#[Medium]
final class IndexPredicateLoweringTest extends TestCase
{
    public function testLowerDischargesQualificationIntoTheSameIndexedSlot(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context(['CREATE TABLE public.orders (id integer, active boolean)']);
        $bare = $analyzer->analyze('CREATE INDEX selected ON public.orders (id) WHERE active IS NULL', $context);
        $qualified = $analyzer->analyze('CREATE INDEX selected ON orders (id) WHERE ((public.orders.active) IS NULL)', $context);

        self::assertInstanceOf(CreateIndex::class, $bare);
        self::assertInstanceOf(CreateIndex::class, $qualified);
        self::assertEquals($bare->predicate, $qualified->predicate);
        self::assertEquals($bare, $analyzer->analyze($qualified->toString(), $context));
        self::assertSame(1, $bare->predicate?->slot);
        self::assertSame('CREATE INDEX "selected" ON "public"."orders" USING btree ("id" ASC NULLS LAST) WHERE ("active" IS NULL)', $qualified->toString());
    }
}

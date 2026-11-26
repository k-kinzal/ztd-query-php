<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Index\AnonymousIndexName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(AnonymousIndexName::class)]
#[Small]
final class AnonymousIndexNameTest extends TestCase
{
    public function testResolveUsesDeclaredOwnersAndEarlierAllocatedNames(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE SCHEMA decoy CREATE TABLE items (id integer) CREATE INDEX items_id_idx2 ON items (id)',
            'CREATE SCHEMA actual CREATE TABLE items (id integer) CREATE SEQUENCE items_id_idx'
                . ' CREATE INDEX ON items (id) CREATE INDEX ON items (id)',
        ]);
        $drop = $semantics->analyze('DROP INDEX actual.items_id_idx1', $context);
        $reindex = $semantics->analyze('REINDEX INDEX actual.items_id_idx2', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertSame('DROP INDEX "actual"."items_id_idx1" RESTRICT', $drop->toString());
        self::assertInstanceOf(ReindexIndex::class, $reindex);
        self::assertSame('actual', $reindex->target->schema->value);
        self::assertSame('items', $reindex->target->table->value);
        self::assertSame('items_id_idx2', $reindex->target->index->value);
    }

    public function testResolveKeepsExpressionIndexOccupancyUnknown(): void
    {
        $context = (new Semantics())->context([
            'CREATE TABLE public.items (id integer)',
            'CREATE INDEX ON public.items ((id + 1))',
            'CREATE INDEX ON public.items (id)',
        ]);

        self::assertInstanceOf(AnalysisContext::class, $context);
        self::assertTrue($context->indexNamespace->hasUnknownNames(new Name('public')));
        self::assertSame([], $context->indexNamespace->indexes);
    }
}

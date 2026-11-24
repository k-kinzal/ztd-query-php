<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\CatalogIndexes;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CatalogIndexes::class)]
#[Medium]
final class CatalogIndexesTest extends TestCase
{
    public function testAddKeepsEmptyNamespacesAndOwnedIndexes(): void
    {
        $context = (new Semantics())->context([
            'CREATE SCHEMA vacant',
            'CREATE TABLE public.items (id integer CONSTRAINT item_key PRIMARY KEY)',
        ]);

        self::assertInstanceOf(AnalysisContext::class, $context);
        self::assertTrue($context->indexNamespace->known(new Name('vacant')));
        self::assertCount(1, $context->indexNamespace->indexes);
        self::assertSame('item_key', $context->indexNamespace->indexes[0]->index->value);
        self::assertFalse($context->indexNamespace->indexes[0]->ordinary);
    }

    public function testAddIndexesLocatesTheIndexInTheOwningSchema(): void
    {
        $context = (new Semantics())->context([
            'CREATE TABLE app.items (id integer)',
            'CREATE INDEX ind ON app.items (id)',
        ]);

        self::assertInstanceOf(AnalysisContext::class, $context);
        self::assertCount(1, $context->indexNamespace->indexes);
        self::assertSame('app', $context->indexNamespace->indexes[0]->schema->value);
        self::assertSame('items', $context->indexNamespace->indexes[0]->table->value);
        self::assertTrue($context->indexNamespace->indexes[0]->ordinary);
    }

    public function testSnapshotCarriesAnOpaqueAutomaticNameInACompleteCatalog(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE app.items (id integer PRIMARY KEY)']);

        self::assertInstanceOf(AnalysisContext::class, $context);
        self::assertTrue($context->complete);
        self::assertTrue($context->indexNamespace->hasUnknownNames(new Name('app')));
        self::assertSame([], $context->indexNamespace->indexes);
    }
}

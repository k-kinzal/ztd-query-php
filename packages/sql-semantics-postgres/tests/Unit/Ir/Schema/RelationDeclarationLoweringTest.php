<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\RelationDeclarationLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RelationDeclarationLowering::class)]
#[Medium]
final class RelationDeclarationLoweringTest extends TestCase
{
    public function testLowerDispatchesOnlyToTheDeclarationOwner(): void
    {
        $semantics = new Semantics();

        $context = $semantics->context(['CREATE TABLE public.indexed (id integer)']);
        self::assertInstanceOf(CreateIndex::class, $semantics->analyze('CREATE INDEX slot_lookup ON indexed (id)', $context));
        self::assertInstanceOf(CreateTable::class, $semantics->analyze('CREATE TABLE sample (id integer)'));
        self::assertInstanceOf(CreateTableAs::class, $semantics->analyze('CREATE TABLE copied AS SELECT 7 AS n'));
        self::assertInstanceOf(CreateView::class, $semantics->analyze('CREATE VIEW current_rows AS SELECT 7 AS n'));
        self::assertInstanceOf(CreateMaterializedView::class, $semantics->analyze('CREATE MATERIALIZED VIEW snapshot AS SELECT 7 AS n'));
    }
}

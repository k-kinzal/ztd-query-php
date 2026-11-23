<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\PersistentDeclarationLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(PersistentDeclarationLowering::class)]
#[Medium]
final class PersistentDeclarationLoweringTest extends TestCase
{
    public function testLowerDispatchesOnlyToTheDeclarationOwner(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(CreateTable::class, $semantics->analyze('CREATE TABLE sample (id integer)'));
        self::assertInstanceOf(CreateView::class, $semantics->analyze('CREATE VIEW current_rows AS SELECT 7 AS n'));
        self::assertInstanceOf(CreateMaterializedView::class, $semantics->analyze('CREATE MATERIALIZED VIEW snapshot AS SELECT 7 AS n'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropRelationLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(DropRelationLowering::class)]
#[Medium]
final class DropRelationLoweringTest extends TestCase
{
    public function testLowerPublishesTheSpecifiedPersistentRelationKind(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE public.original (n integer)',
            'CREATE VIEW public.derived AS SELECT n FROM public.original',
        ]);

        self::assertInstanceOf(DropTable::class, $semantics->analyze('DROP TABLE original', $context));
        self::assertInstanceOf(DropView::class, $semantics->analyze('DROP VIEW derived', $context));
    }
}

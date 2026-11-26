<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\ReindexLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ReindexLowering::class)]
#[Small]
final class ReindexLoweringTest extends TestCase
{
    public function testLowerSelectsTheBuiltinBeforeALikeNamedTable(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE main.nocase (code TEXT)']);
        $value = $semantics->analyze('REINDEX nocase', $context);
        $table = $semantics->analyze('REINDEX main.nocase', $context);

        self::assertInstanceOf(Reindex::class, $value);
        self::assertSame(Collation::NoCase, $value->target);
        self::assertInstanceOf(Reindex::class, $table);
        self::assertInstanceOf(RelationTarget::class, $table->target);
        self::assertSame('REINDEX "main"."nocase"', $table->toString());
    }
}

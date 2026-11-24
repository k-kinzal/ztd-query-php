<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Scope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabaseLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSystemCatalogs;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ReindexDatabaseLowering::class)]
#[Medium]
final class ReindexDatabaseLoweringTest extends TestCase
{
    public function testLowerBindsBothSelectorsToTheCurrentDatabase(): void
    {
        $semantics = new Semantics(currentDatabase: new Identifier('sales.data'));
        $requested = $semantics->analyze('REINDEX (CONCURRENTLY TRUE) DATABASE');
        $system = $semantics->analyze('REINDEX (CONCURRENTLY ON, CONCURRENTLY OFF) SYSTEM "sales.data"');

        self::assertInstanceOf(ReindexDatabase::class, $requested);
        self::assertSame('sales.data', $requested->database->value);
        self::assertSame(IndexRebuildMode::Concurrent, $requested->mode);
        self::assertInstanceOf(ReindexSystemCatalogs::class, $system);
        self::assertSame('REINDEX SYSTEM "sales.data"', $system->toString());
    }
}

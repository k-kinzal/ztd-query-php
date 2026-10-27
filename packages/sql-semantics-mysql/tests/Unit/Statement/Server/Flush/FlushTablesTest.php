<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Flush;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(FlushTables::class)]
#[Medium]
final class FlushTablesTest extends TestCase
{
    public function testRenderWritesTheLock(): void
    {
        self::assertSame('FLUSH TABLE WITH READ LOCK', (new Semantics(Dialect::MySql))->analyze('flush tables with read lock')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('FLUSH TABLES t FOR EXPORT', [$table]);
        self::assertInstanceOf(FlushTables::class, $operation);

        self::assertInstanceOf(DeclaredTable::class, $operation->tables[0]->relationInformation()->table);
    }
}

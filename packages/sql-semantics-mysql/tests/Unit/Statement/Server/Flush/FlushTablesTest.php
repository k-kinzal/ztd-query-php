<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Flush;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FlushTables::class)]
#[Medium]
final class FlushTablesTest extends TestCase
{
    public function testRenderWritesTheLock(): void
    {
        self::assertSame('FLUSH TABLE WITH READ LOCK', (new Inspection())->inspect('flush tables with read lock')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->inspect('FLUSH TABLES t FOR EXPORT', [$table]);
        self::assertInstanceOf(FlushTables::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}

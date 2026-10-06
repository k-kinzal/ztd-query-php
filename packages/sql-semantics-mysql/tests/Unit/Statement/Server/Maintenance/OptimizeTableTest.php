<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(OptimizeTable::class)]
#[Medium]
final class OptimizeTableTest extends TestCase
{
    public function testRenderWritesNoWriteToBinlog(): void
    {
        self::assertSame('OPTIMIZE NO_WRITE_TO_BINLOG TABLE t', (new Semantics(Dialect::MySql))->analyze('optimize no_write_to_binlog tables t')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('OPTIMIZE TABLE t', [$table]);
        self::assertInstanceOf(OptimizeTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}

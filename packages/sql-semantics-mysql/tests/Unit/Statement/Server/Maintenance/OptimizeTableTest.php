<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OptimizeTable::class)]
#[Medium]
final class OptimizeTableTest extends TestCase
{
    public function testRenderWritesNoWriteToBinlog(): void
    {
        self::assertSame('OPTIMIZE NO_WRITE_TO_BINLOG TABLE t', (new Inspection())->inspect('optimize no_write_to_binlog tables t')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->inspect('OPTIMIZE TABLE t', [$table]);
        self::assertInstanceOf(OptimizeTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}

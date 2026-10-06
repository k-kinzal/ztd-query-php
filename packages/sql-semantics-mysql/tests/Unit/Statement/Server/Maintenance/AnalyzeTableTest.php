<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(AnalyzeTable::class)]
#[Medium]
final class AnalyzeTableTest extends TestCase
{
    public function testRenderWritesNoWriteToBinlogAndTheHistogram(): void
    {
        self::assertSame('ANALYZE NO_WRITE_TO_BINLOG TABLE t UPDATE HISTOGRAM ON a', (new Semantics(Dialect::MySql))->analyze('analyze local table t update histogram on a')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('ANALYZE TABLE t', [$table]);
        self::assertInstanceOf(AnalyzeTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }

    public function testDeriveStatementChecksTheHistogram(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ANALYZE TABLE t, u DROP HISTOGRAM ON a');

        self::assertInstanceOf(HistogramTables::class, $operation->facts->diagnostics[0]);
    }
}

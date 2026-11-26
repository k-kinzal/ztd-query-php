<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Vacuum::class)]
#[Medium]
final class VacuumTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $operation = (new Inspection())->diagnose('VACUUM a, s.b');
        self::assertInstanceOf(Vacuum::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->targets[0])->table);
    }

    public function testDeriveStatementReportsColumnsWithoutAnalyze(): void
    {
        self::assertSame(['ANALYZE option must be specified when a column list is provided'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('VACUUM FULL t (a)')->facts->diagnostics));
    }

    public function testDeriveStatementReportsAnUnknownOption(): void
    {
        self::assertSame(['unrecognized VACUUM option "fast"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('VACUUM (fast) t')->facts->diagnostics));
    }

    public function testRenderWritesEachSyntax(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['VACUUM', 'VACUUM FREEZE ANALYZE t (a)', 'VACUUM (full FALSE, index_cleanup auto) t, u'],
            [$semantics->diagnose('VACUUM')->toString(), $semantics->diagnose('VACUUM FREEZE ANALYZE t (a)')->toString(), $semantics->diagnose('VACUUM (FULL false, INDEX_CLEANUP auto) t, u')->toString()],
        );
    }
}

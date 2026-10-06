<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Vacuum::class)]
#[Medium]
final class VacuumTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('VACUUM a, s.b');
        self::assertInstanceOf(Vacuum::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->targets[0])->table);
    }

    public function testDeriveStatementReportsColumnsWithoutAnalyze(): void
    {
        self::assertSame(['ANALYZE option must be specified when a column list is provided'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('VACUUM FULL t (a)')->facts->diagnostics));
    }

    public function testDeriveStatementReportsAnUnknownOption(): void
    {
        self::assertSame(['unrecognized VACUUM option "fast"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('VACUUM (fast) t')->facts->diagnostics));
    }

    public function testRenderWritesEachSyntax(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['VACUUM', 'VACUUM FREEZE ANALYZE t (a)', 'VACUUM (full FALSE, index_cleanup auto) t, u'],
            [$semantics->analyze('VACUUM')->toString(), $semantics->analyze('VACUUM FREEZE ANALYZE t (a)')->toString(), $semantics->analyze('VACUUM (FULL false, INDEX_CLEANUP auto) t, u')->toString()],
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Cluster::class)]
#[Medium]
final class ClusterTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $operation = (new Inspection())->diagnose('CLUSTER s.t USING i');
        self::assertInstanceOf(Cluster::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementReportsAnOptionValueThatIsNotBoolean(): void
    {
        self::assertSame(['verbose requires a Boolean value'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('CLUSTER (VERBOSE 2)')->facts->diagnostics));
    }

    public function testRenderWritesEachForm(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['CLUSTER', 'CLUSTER VERBOSE', 'CLUSTER VERBOSE i ON s.t', 'CLUSTER (verbose) t USING i', 'CLUSTER (verbose)', 'CLUSTER t'],
            [
                $semantics->diagnose('CLUSTER')->toString(),
                $semantics->diagnose('CLUSTER VERBOSE')->toString(),
                $semantics->diagnose('CLUSTER VERBOSE i ON s.t')->toString(),
                $semantics->diagnose('CLUSTER (VERBOSE) t USING i')->toString(),
                $semantics->diagnose('CLUSTER (VERBOSE)')->toString(),
                $semantics->diagnose('CLUSTER t')->toString(),
            ],
        );
    }

    public function testDeriveStatementReportsAView(): void
    {
        $semantics = new Inspection();
        $context = [$semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('CLUSTER v', $context)->facts->diagnostics));
    }
}

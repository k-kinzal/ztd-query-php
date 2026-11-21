<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;

#[CoversClass(Cluster::class)]
#[Medium]
final class ClusterTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $operation = (new Semantics())->inspect('CLUSTER s.t USING i');
        self::assertInstanceOf(Cluster::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementReportsAnOptionValueThatIsNotBoolean(): void
    {
        self::assertSame(['verbose requires a Boolean value'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics())->inspect('CLUSTER (VERBOSE 2)')->facts->diagnostics));
    }

    public function testRenderWritesEachForm(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            ['CLUSTER', 'CLUSTER VERBOSE', 'CLUSTER VERBOSE i ON s.t', 'CLUSTER (verbose) t USING i', 'CLUSTER (verbose)', 'CLUSTER t'],
            [
                $semantics->inspect('CLUSTER')->toString(),
                $semantics->inspect('CLUSTER VERBOSE')->toString(),
                $semantics->inspect('CLUSTER VERBOSE i ON s.t')->toString(),
                $semantics->inspect('CLUSTER (VERBOSE) t USING i')->toString(),
                $semantics->inspect('CLUSTER (VERBOSE)')->toString(),
                $semantics->inspect('CLUSTER t')->toString(),
            ],
        );
    }

    public function testDeriveStatementReportsAView(): void
    {
        $semantics = new Semantics();
        $context = [$semantics->inspect('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CLUSTER v', $context)->facts->diagnostics));
    }
}

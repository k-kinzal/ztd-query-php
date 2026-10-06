<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Cluster::class)]
#[Medium]
final class ClusterTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CLUSTER s.t USING i');
        self::assertInstanceOf(Cluster::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementReportsAnOptionValueThatIsNotBoolean(): void
    {
        self::assertSame(['verbose requires a Boolean value'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('CLUSTER (VERBOSE 2)')->facts->diagnostics));
    }

    public function testRenderWritesEachForm(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['CLUSTER', 'CLUSTER VERBOSE', 'CLUSTER VERBOSE i ON s.t', 'CLUSTER (verbose) t USING i', 'CLUSTER (verbose)', 'CLUSTER t'],
            [
                $semantics->analyze('CLUSTER')->toString(),
                $semantics->analyze('CLUSTER VERBOSE')->toString(),
                $semantics->analyze('CLUSTER VERBOSE i ON s.t')->toString(),
                $semantics->analyze('CLUSTER (VERBOSE) t USING i')->toString(),
                $semantics->analyze('CLUSTER (VERBOSE)')->toString(),
                $semantics->analyze('CLUSTER t')->toString(),
            ],
        );
    }

    public function testDeriveStatementReportsAView(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CLUSTER v', $context)->facts->diagnostics));
    }
}

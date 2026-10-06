<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateStatistics::class)]
#[Medium]
final class CreateStatisticsTest extends TestCase
{
    public function testDeriveStatementResolvesTheKeysInTheFromItems(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE STATISTICS s ON a, (b + zz) FROM t', $context);
        self::assertSame([
          0 => 'Column zz does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE STATISTICS IF NOT EXISTS x.s (ndistinct, mcv) ON a, (a + b), lower(c) FROM t', []);
        self::assertSame('CREATE STATISTICS IF NOT EXISTS x.s (ndistinct, mcv) ON a, (a + b), lower(c) FROM t', $statement->toString());
    }

    public function testDeriveStatementReportsAView(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a'), $semantics->analyze('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')];
        self::assertSame(['cannot define statistics for relation "v"'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE STATISTICS st ON a, (a + 1) FROM v', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE STATISTICS st ON a, (a + 1) FROM m', $context)->facts->diagnostics));
    }
}

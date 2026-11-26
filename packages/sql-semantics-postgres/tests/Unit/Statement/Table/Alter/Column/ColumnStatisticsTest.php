<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnStatistics::class)]
#[Medium]
final class ColumnStatisticsTest extends TestCase
{
    public function testDeriveClauseChecksANamedColumn(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('ALTER TABLE t ALTER zz SET STATISTICS 10', $context);
        self::assertSame([
          0 => 'column "zz" does not exist',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER INDEX i ALTER COLUMN 2 SET STATISTICS DEFAULT', []);
        self::assertSame('ALTER INDEX i ALTER 2 SET STATISTICS DEFAULT', $statement->toString());
    }
}

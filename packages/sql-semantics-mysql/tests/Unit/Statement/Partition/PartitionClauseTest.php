<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(PartitionClause::class)]
#[Medium]
final class PartitionClauseTest extends TestCase
{
    public function testDerivePartitioningDerivesTheMethodAndTheValues(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $alter = $semantics->analyze('ALTER TABLE t PARTITION BY RANGE (b) (PARTITION p VALUES LESS THAN (c))', [$table]);

        self::assertSame(['Column b does not exist.', 'Column c does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testRenderWritesTheCountsAndTheDefinitions(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY HASH (a) PARTITIONS 2 (PARTITION p0, PARTITION p1)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY HASH (a) PARTITIONS 2 (PARTITION p0, PARTITION p1)')->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;

#[CoversClass(AddPartition::class)]
#[Medium]
final class AddPartitionTest extends TestCase
{
    public function testDeriveCommandDerivesTheValues(): void
    {
        self::assertCount(1, (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (x))')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION NO_WRITE_TO_BINLOG PARTITIONS 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t ADD PARTITION LOCAL PARTITIONS 2')->toString());
    }
}

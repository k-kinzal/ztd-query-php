<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition;

#[CoversClass(ReorganizePartition::class)]
#[Medium]
final class ReorganizePartitionTest extends TestCase
{
    public function testDeriveCommandDerivesTheValues(): void
    {
        self::assertCount(1, (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t REORGANIZE PARTITION p INTO (PARTITION q VALUES LESS THAN (x))')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t REORGANIZE PARTITION', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t REORGANIZE PARTITION')->toString());
    }
}

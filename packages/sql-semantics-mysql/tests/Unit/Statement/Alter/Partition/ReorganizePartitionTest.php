<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ReorganizePartition::class)]
#[Medium]
final class ReorganizePartitionTest extends TestCase
{
    public function testDeriveCommandDerivesTheValues(): void
    {
        self::assertCount(1, (new Inspection())->diagnose('ALTER TABLE t REORGANIZE PARTITION p INTO (PARTITION q VALUES LESS THAN (x))')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t REORGANIZE PARTITION', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t REORGANIZE PARTITION')->toString());
    }
}

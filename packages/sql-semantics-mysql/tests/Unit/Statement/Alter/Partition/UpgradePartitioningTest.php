<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\UpgradePartitioning;

#[CoversClass(UpgradePartitioning::class)]
#[Medium]
final class UpgradePartitioningTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics('mysql-5.7.44'))->analyze('ALTER TABLE t UPGRADE PARTITIONING')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t UPGRADE PARTITIONING', (new Semantics('mysql-5.7.44'))->analyze('ALTER TABLE t UPGRADE PARTITIONING')->toString());
    }
}

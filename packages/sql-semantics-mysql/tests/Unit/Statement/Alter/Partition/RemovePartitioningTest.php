<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\RemovePartitioning;

#[CoversClass(RemovePartitioning::class)]
#[Medium]
final class RemovePartitioningTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t REMOVE PARTITIONING')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t FORCE REMOVE PARTITIONING', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t FORCE REMOVE PARTITIONING')->toString());
    }
}

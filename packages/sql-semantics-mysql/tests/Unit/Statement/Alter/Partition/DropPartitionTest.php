<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition;

#[CoversClass(DropPartition::class)]
#[Medium]
final class DropPartitionTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t DROP PARTITION p')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t DROP PARTITION p0, p1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t DROP PARTITION p0, p1')->toString());
    }
}

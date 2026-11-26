<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropPartition::class)]
#[Medium]
final class DropPartitionTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Inspection())->inspect('ALTER TABLE t DROP PARTITION p')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t DROP PARTITION p0, p1', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t DROP PARTITION p0, p1')->toString());
    }
}

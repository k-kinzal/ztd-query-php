<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(MaintainPartitions::class)]
#[Medium]
final class MaintainPartitionsTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Inspection())->inspect('ALTER TABLE t REBUILD PARTITION p')->facts->diagnostics);
    }

    public function testRenderWritesTheIgnoredOption(): void
    {
        self::assertSame('ALTER TABLE t OPTIMIZE PARTITION p NO_WRITE_TO_BINLOG', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t OPTIMIZE PARTITION p LOCAL')->toString());
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t TRUNCATE PARTITION ALL', (new Inspection())->inspect('ALTER TABLE t TRUNCATE PARTITION ALL')->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions;

#[CoversClass(MaintainPartitions::class)]
#[Medium]
final class MaintainPartitionsTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t REBUILD PARTITION p')->facts->diagnostics);
    }

    public function testRenderWritesTheIgnoredOption(): void
    {
        self::assertSame('ALTER TABLE t OPTIMIZE PARTITION p NO_WRITE_TO_BINLOG', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t OPTIMIZE PARTITION p LOCAL')->toString());
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t TRUNCATE PARTITION ALL', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t TRUNCATE PARTITION ALL')->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry;

#[CoversClass(PartitionEntry::class)]
#[Medium]
final class PartitionEntryTest extends TestCase
{
    public function testDeriveStatementReportsTheRefusal(): void
    {
        $entry = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('PARTITION BY KEY ()');

        self::assertInstanceOf(PartitionEntryRefused::class, $entry->facts->diagnostics[0]);
    }

    public function testRenderWritesTheClause(): void
    {
        self::assertSame('PARTITION BY HASH (1) PARTITIONS 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('partition by hash (1) partitions 2')->toString());
    }
}

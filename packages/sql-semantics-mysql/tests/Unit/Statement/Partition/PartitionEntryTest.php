<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PartitionEntry::class)]
#[Medium]
final class PartitionEntryTest extends TestCase
{
    public function testDeriveStatementReportsTheRefusal(): void
    {
        $entry = (new Inspection('mysql-5.6.51'))->diagnose('PARTITION BY KEY ()');

        self::assertInstanceOf(PartitionEntryRefused::class, $entry->facts->diagnostics[0]);
    }

    public function testRenderWritesTheClause(): void
    {
        self::assertSame('PARTITION BY HASH (1) PARTITIONS 2', (new Inspection('mysql-5.7.44'))->diagnose('partition by hash (1) partitions 2')->toString());
    }
}

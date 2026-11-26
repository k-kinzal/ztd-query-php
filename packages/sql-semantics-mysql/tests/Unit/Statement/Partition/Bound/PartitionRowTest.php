<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PartitionRow::class)]
#[Medium]
final class PartitionRowTest extends TestCase
{
    public function testDeriveRowDerivesTheValuesWithoutColumns(): void
    {
        self::assertSame('Column a does not exist.', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY RANGE (a) (PARTITION p VALUES LESS THAN (a))')->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheValues(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES LESS THAN (1, MAXVALUE))', (new Inspection())->diagnose('ALTER TABLE t ADD PARTITION (PARTITION p VALUES LESS THAN (1, MAXVALUE))')->toString());
    }
}

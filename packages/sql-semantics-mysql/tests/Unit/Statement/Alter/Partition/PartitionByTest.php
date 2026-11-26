<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PartitionBy::class)]
#[Medium]
final class PartitionByTest extends TestCase
{
    public function testDeriveCommandDerivesThePartitioningInTheTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');

        $alter = $semantics->diagnose('ALTER TABLE t ADD b INT PARTITION BY HASH (a + b + c)', [$table]);

        self::assertCount(1, $alter->facts->diagnostics);
        self::assertSame('Column c does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t FORCE PARTITION BY KEY () PARTITIONS 2', (new Inspection())->diagnose('ALTER TABLE t FORCE PARTITION BY KEY () PARTITIONS 2')->toString());
    }
}

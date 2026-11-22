<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ExchangePartition;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ExchangePartition::class)]
#[Medium]
final class ExchangePartitionTest extends TestCase
{
    public function testDeriveCommandResolvesTheOtherTable(): void
    {
        $semantics = new Inspection();
        $t = $semantics->inspect('CREATE TABLE t (a INT)');
        $u = $semantics->inspect('CREATE TABLE u (a INT)');
        $alter = $semantics->inspect('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u', [$t, $u]);

        self::assertInstanceOf(AlterTable::class, $alter->statement);
        self::assertEquals(new DeclaredTable($u->declarations()[0]), $alter->facts->relation($alter->statement->commands[0])->table);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE db.u WITHOUT VALIDATION', (new Inspection())->inspect('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE db.u WITHOUT VALIDATION')->toString());
    }
}

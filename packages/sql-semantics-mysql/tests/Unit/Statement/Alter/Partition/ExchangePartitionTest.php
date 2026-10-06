<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ExchangePartition;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(ExchangePartition::class)]
#[Medium]
final class ExchangePartitionTest extends TestCase
{
    public function testDeriveCommandResolvesTheOtherTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT)');
        $u = $semantics->analyze('CREATE TABLE u (a INT)');
        $alter = $semantics->analyze('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u', [$t, $u]);

        self::assertInstanceOf(AlterTable::class, $alter->statement);
        self::assertEquals(new DeclaredTable($u->declarations()[0]), $alter->facts->relation($alter->statement->commands[0])->table);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE db.u WITHOUT VALIDATION', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE db.u WITHOUT VALIDATION')->toString());
    }
}

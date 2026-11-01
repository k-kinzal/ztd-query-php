<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Replica;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(StopReplica::class)]
#[Medium]
final class StopReplicaTest extends TestCase
{
    public function testDeriveStatementRejectsAChannelOfMySql56(): void
    {
        $this->expectExceptionMessage('A replication channel needs MySQL 5.7 or later.');

        (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), new StopReplica(Terminology::Legacy, [], new Text('c')), new \SqlSemantics\Statement\Source\SourceMap());
    }

    public function testRenderWritesTheThreads(): void
    {
        self::assertSame('STOP REPLICA SQL_THREAD, RELAY_THREAD', (new Semantics(Dialect::MySql))->analyze('stop replica sql_thread, relay_thread')->toString());
    }

    public function testValueRejectsIncompatibleChannel(): void
    {
        $this->expectExceptionMessage('A replication channel is quoted text.');
        new StopReplica(Terminology::Legacy, channel: new Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}

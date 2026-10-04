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
use SqlSemantics\Statement\Operation;

#[CoversClass(StopReplica::class)]
#[Medium]
final class StopReplicaTest extends TestCase
{
    public function testDeriveStatementRejectsAChannelOfMySql56(): void
    {
        $this->expectExceptionMessage('A replication channel needs MySQL 5.7 or later.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), new StopReplica(Terminology::Legacy, [], new Text('c')));
    }

    public function testRenderWritesTheThreads(): void
    {
        self::assertSame('STOP REPLICA SQL_THREAD, IO_THREAD', (new Semantics(Dialect::MySql))->analyze('stop replica sql_thread, relay_thread')->toString());
    }
}

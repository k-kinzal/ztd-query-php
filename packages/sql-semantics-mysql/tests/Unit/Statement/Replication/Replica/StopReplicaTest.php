<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Replica;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StopReplica::class)]
#[Medium]
final class StopReplicaTest extends TestCase
{
    public function testDeriveStatementRejectsAChannelOfMySql56(): void
    {
        $this->expectExceptionMessage('A replication channel needs MySQL 5.7 or later.');

        new InspectedStatement((new Inspection('mysql-5.6.51'))->context([]), new StopReplica(Terminology::Legacy, [], new Text('c')));
    }

    public function testRenderWritesTheThreads(): void
    {
        self::assertSame('STOP REPLICA SQL_THREAD, IO_THREAD', (new Inspection())->inspect('stop replica sql_thread, relay_thread')->toString());
    }
}

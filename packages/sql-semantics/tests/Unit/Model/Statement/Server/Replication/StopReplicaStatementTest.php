<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Statement\Server\Replication\StopReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StopReplicaStatement::class)]
#[Medium]
final class StopReplicaStatementTest extends TestCase
{
    public function testWithOriginPreservesThreadsAndChannel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("STOP SLAVE RELAY_THREAD FOR CHANNEL 'c'");
        self::assertInstanceOf(StopReplicaStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([ReplicaThread::Receiver], $copy->threads);
        self::assertSame("STOP SLAVE IO_THREAD FOR CHANNEL 'c'", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithThreadsReplacesTheThreadsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('STOP REPLICA');
        self::assertInstanceOf(StopReplicaStatement::class, $statement);
        self::assertSame('STOP REPLICA SQL_THREAD, IO_THREAD', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withThreads([ReplicaThread::Applier, ReplicaThread::Receiver])));
        self::assertSame([], $statement->threads);
    }

    public function testWithChannelReplacesTheChannelImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("STOP REPLICA FOR CHANNEL 'c'");
        self::assertInstanceOf(StopReplicaStatement::class, $statement);
        self::assertSame('STOP REPLICA', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withChannel(null)));
        self::assertSame('c', $statement->channel);
    }

    public function testRejectsAChannelBeforeMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('STOP SLAVE');
        $this->expectException(InvalidStructure::class);
        new StopReplicaStatement($statement->origin, [], 'c');
    }
}

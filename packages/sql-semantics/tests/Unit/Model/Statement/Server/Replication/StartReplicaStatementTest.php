<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\GapsClosed;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StartReplicaStatement::class)]
#[Medium]
final class StartReplicaStatementTest extends TestCase
{
    public function testWithOriginPreservesEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA IO_THREAD UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4 USER = 'u' PLUGIN_DIR = 'd' FOR CHANNEL 'c'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("START REPLICA IO_THREAD UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4 USER = 'u' PLUGIN_DIR = 'd' FOR CHANNEL 'c'", $copy->toString());
    }

    public function testWithThreadsReplacesTheThreadsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START REPLICA');
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame('START REPLICA SQL_THREAD', $statement->withThreads([ReplicaThread::Applier])->toString());
        self::assertSame([], $statement->threads);
    }

    public function testWithUntilReplacesTheStopPointImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START REPLICA');
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame('START REPLICA UNTIL SQL_AFTER_MTS_GAPS', $statement->withUntil(new GapsClosed())->toString());
        self::assertNull($statement->until);
    }

    public function testWithCredentialsReplacesTheCredentialsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'u'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame('START REPLICA', $statement->withCredentials([])->toString());
        self::assertCount(1, $statement->credentials);
    }

    public function testWithChannelReplacesTheChannelImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START REPLICA');
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame("START REPLICA FOR CHANNEL 'east'", $statement->withChannel('east')->toString());
        self::assertNull($statement->channel);
    }

    public function testRejectsCredentialsForTheSqlThreadAlone(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'u'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new StartReplicaStatement($statement->origin, [ReplicaThread::Applier], null, $statement->credentials);
    }

    public function testRejectsCredentialsOutOfOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'u' PASSWORD = 'p'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new StartReplicaStatement($statement->origin, [], null, array_reverse($statement->credentials));
    }

    public function testRejectsARepeatedCredential(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'u'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new StartReplicaStatement($statement->origin, [], null, [$statement->credentials[0], $statement->credentials[0]]);
    }

    public function testRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectExceptionObject(new InvalidStructure('START REPLICA requires MySQL.'));
        new StartReplicaStatement($origin);
    }

    public function testRejectsAChannelWithALineFeed(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new StartReplicaStatement($origin, channel: "a\nb");
    }
}

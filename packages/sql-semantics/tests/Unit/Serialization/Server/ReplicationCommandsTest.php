<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\GapsClosed;
use SqlSemantics\Model\Statement\Server\Replication\StartGroupReplicationStatement;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Statement\Server\Replication\StopGroupReplicationStatement;
use SqlSemantics\Model\Statement\Server\Replication\StopReplicaStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\ReplicationCommands;

#[CoversClass(ReplicationCommands::class)]
#[Medium]
final class ReplicationCommandsTest extends TestCase
{
    public function testChannelWritesTheNameAsAStringOrNothing(): void
    {
        self::assertSame([], ReplicationCommands::channel(null));
        self::assertCount(2, ReplicationCommands::channel("a'b"));
    }

    public function testReplicaWritesTheReleaseVocabulary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("START SLAVE UNTIL RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame('start-replica', ReplicationCommands::replica($statement)->role);
        self::assertSame("START SLAVE UNTIL RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4", $statement->toString());
    }

    public function testGroupWritesCredentialsSeparatedByCommas(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START GROUP_REPLICATION USER = 'u', PASSWORD = 'p'");
        self::assertInstanceOf(StartGroupReplicationStatement::class, $statement);
        self::assertSame('start-group-replication', ReplicationCommands::group($statement)->role);
        self::assertSame("START GROUP_REPLICATION USER = 'u', PASSWORD = 'p'", $statement->toString());
    }

    public function testUntilWritesTheGapsCondition(): void
    {
        self::assertSame('keyword', ReplicationCommands::until(new GapsClosed(), false)->role);
    }

    public function testCredentialsWritesOneTreePerCredentialForReplicas(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'u' PASSWORD = 'p'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertCount(2, ReplicationCommands::credentials($statement->credentials, false));
        self::assertCount(1, ReplicationCommands::credentials($statement->credentials, true));
    }

    public function testAssignmentWritesKeywordAndValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA USER = 'u'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame('replication-option', ReplicationCommands::assignment('USER', $statement->credentials[0]->value)->role);
    }

    #[TestWith(['mysql-5.7.44', "STOP SLAVE IO_THREAD, SQL_THREAD FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.4.7', "STOP REPLICA IO_THREAD FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.4.7', 'STOP REPLICA'])]
    public function testReplicaWritesTheStoppedThreadsAndChannel(string $version, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(StopReplicaStatement::class, $statement);
        self::assertSame($sql, ReplicationCommands::replica($statement)->toString());
    }

    #[TestWith(['mysql-5.7.44', "START SLAVE UNTIL MASTER_LOG_FILE = 'f', MASTER_LOG_POS = 4"])]
    #[TestWith(['mysql-8.4.7', "START REPLICA IO_THREAD UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4 USER = 'u' PASSWORD = 'p' FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.4.7', "START REPLICA UNTIL SQL_BEFORE_GTIDS = 'uuid:1-5'"])]
    public function testReplicaWritesTheStartedThreadsStopPointCredentialsAndChannel(string $version, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertSame($sql, ReplicationCommands::replica($statement)->toString());
    }

    public function testGroupWritesTheStop(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('STOP GROUP_REPLICATION');
        self::assertInstanceOf(StopGroupReplicationStatement::class, $statement);
        self::assertSame('STOP GROUP_REPLICATION', ReplicationCommands::group($statement)->toString());
    }

    public function testGroupWritesAStartWithoutCredentials(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START GROUP_REPLICATION');
        self::assertInstanceOf(StartGroupReplicationStatement::class, $statement);
        self::assertSame('START GROUP_REPLICATION', ReplicationCommands::group($statement)->toString());
    }

    public function testChannelWritesTheEscapedName(): void
    {
        self::assertSame("FOR CHANNEL 'a''b'", (new \SqlSemantics\Model\Sql\Tree('channel', ReplicationCommands::channel("a'b")))->toString());
    }
}

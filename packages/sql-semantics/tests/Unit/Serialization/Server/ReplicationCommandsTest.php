<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\GapsClosed;
use SqlSemantics\Model\Statement\Server\Replication\StartGroupReplicationStatement;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
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
}

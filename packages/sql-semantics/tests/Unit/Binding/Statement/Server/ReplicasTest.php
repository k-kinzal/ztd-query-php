<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Replicas;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\CredentialOption;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Statement\Server\Replication\StartGroupReplicationStatement;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Statement\Server\Replication\StopGroupReplicationStatement;
use SqlSemantics\Model\Statement\Server\Replication\StopReplicaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Replicas::class)]
#[Medium]
final class ReplicasTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'SLAVE', 'SLAVE'])]
    #[TestWith(['mysql-5.7.44', 'SLAVE', 'SLAVE'])]
    #[TestWith(['mysql-8.0.44', 'SLAVE', 'REPLICA'])]
    #[TestWith(['mysql-8.1.0', 'REPLICA', 'REPLICA'])]
    #[TestWith(['mysql-8.2.0', 'SLAVE', 'REPLICA'])]
    #[TestWith(['mysql-8.3.0', 'REPLICA', 'REPLICA'])]
    #[TestWith(['mysql-8.4.7', 'REPLICA', 'REPLICA'])]
    #[TestWith(['mysql-9.0.1', 'REPLICA', 'REPLICA'])]
    #[TestWith(['mysql-9.1.0', 'REPLICA', 'REPLICA'])]
    public function testBindReadsStartAndStopInEveryVocabulary(string $version, string $written, string $spelled): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $start = $binder->bind('START ' . $written . " IO_THREAD, SQL_THREAD USER = 'u' PASSWORD = 'p'");
        self::assertInstanceOf(StartReplicaStatement::class, $start);
        self::assertSame([ReplicaThread::Receiver, ReplicaThread::Applier], $start->threads);
        self::assertSame('START ' . $spelled . " IO_THREAD, SQL_THREAD USER = 'u' PASSWORD = 'p'", $start->toString());
        self::assertSame($start->toString(), $binder->bind($start->toString())->toString());
        $stop = $binder->bind('STOP ' . $written . ' SQL_THREAD');
        self::assertInstanceOf(StopReplicaStatement::class, $stop);
        self::assertSame('STOP ' . $spelled . ' SQL_THREAD', $stop->toString());
    }

    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsGroupReplication(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        self::assertInstanceOf(StartGroupReplicationStatement::class, $binder->bind('START GROUP_REPLICATION'));
        self::assertInstanceOf(StopGroupReplicationStatement::class, $binder->bind('STOP GROUP_REPLICATION'));
    }

    public function testBindDiagnosesCredentialsForTheSqlThreadAlone(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START REPLICA SQL_THREAD USER = 'u'");
    }

    public function testBindDiagnosesAnEmptyGroupReplicationUser(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START GROUP_REPLICATION USER = ''");
    }

    public function testThreadsReadsRelayThreadAsTheReceiver(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('STOP REPLICA RELAY_THREAD');
        self::assertInstanceOf(StopReplicaStatement::class, $statement);
        self::assertSame([ReplicaThread::Receiver], $statement->threads);
    }

    public function testCredentialsReadsEveryOptionInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START GROUP_REPLICATION DEFAULT_AUTH = 'a', USER = 'u'");
        self::assertInstanceOf(StartGroupReplicationStatement::class, $statement);
        self::assertSame([CredentialOption::DefaultAuth, CredentialOption::User], array_column($statement->credentials, 'option'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Session\ReplicationReports;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogEventsStatement;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogsStatement;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogStatusStatement;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowRelayLogEventsStatement;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowReplicasStatement;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowReplicaStatusStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationReports::class)]
#[Medium]
final class ReplicationReportsTest extends TestCase
{
    /**
     * @param class-string<ShowBinaryLogsStatement|ShowBinaryLogStatusStatement|ShowReplicasStatement|ShowReplicaStatusStatement> $expected
     */
    #[TestWith(['mysql-5.6.51', 'SHOW MASTER LOGS', ShowBinaryLogsStatement::class])]
    #[TestWith(['mysql-8.0.44', 'SHOW MASTER STATUS', ShowBinaryLogStatusStatement::class])]
    #[TestWith(['mysql-8.4.7', 'SHOW BINARY LOG STATUS', ShowBinaryLogStatusStatement::class])]
    #[TestWith(['mysql-5.7.44', 'SHOW SLAVE HOSTS', ShowReplicasStatement::class])]
    #[TestWith(['mysql-9.1.0', 'SHOW REPLICAS', ShowReplicasStatement::class])]
    #[TestWith(['mysql-8.0.44', 'SHOW SLAVE STATUS', ShowReplicaStatusStatement::class])]
    public function testBindNormalizesSynonymousSpellings(string $version, string $sql, string $expected): void
    {
        self::assertInstanceOf($expected, (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testLogDecodesTheFileName(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("SHOW BINLOG EVENTS IN 'a\\\\b''c'");
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertSame("a\\b'c", $statement->log);
    }

    public function testPositionKeepsTheWrittenNumber(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW RELAYLOG EVENTS FROM 18446744073709551616');
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        self::assertSame('18446744073709551616', $statement->position?->text);
    }

    public function testChannelDecodesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW REPLICA STATUS FOR CHANNEL 'a''b'");
        self::assertInstanceOf(ShowReplicaStatusStatement::class, $statement);
        self::assertSame("a'b", $statement->channel);
    }

    public function testChannelRejectsALineFeed(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ChannelName->message());
        $binder->bind("SHOW REPLICA STATUS FOR CHANNEL 'a\\nb'");
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Change\Changes;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\Source\IgnoredServers;
use SqlSemantics\Model\Configuration\Replication\Source\SourceFlag;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Changes::class)]
#[Medium]
final class ChangesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "CHANGE MASTER TO MASTER_HOST = 'h', MASTER_LOG_FILE = 'b.000001', MASTER_LOG_POS = 4", "CHANGE MASTER TO MASTER_HOST = 'h', MASTER_LOG_FILE = 'b.000001', MASTER_LOG_POS = 4"])]
    #[TestWith(['mysql-5.7.44', "CHANGE MASTER TO MASTER_TLS_VERSION = 'TLSv1.2' FOR CHANNEL 'c'", "CHANGE MASTER TO MASTER_TLS_VERSION = 'TLSv1.2' FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.0.44', "CHANGE MASTER TO MASTER_HOST = 'h', SOURCE_PORT = 1", "CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h', SOURCE_PORT = 1"])]
    #[TestWith(['mysql-8.1.0', 'CHANGE MASTER TO GET_MASTER_PUBLIC_KEY = 1', 'CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY = 1'])]
    #[TestWith(['mysql-8.2.0', "CHANGE REPLICATION SOURCE TO SOURCE_USER = 'u'", "CHANGE REPLICATION SOURCE TO SOURCE_USER = 'u'"])]
    #[TestWith(['mysql-8.3.0', 'CHANGE REPLICATION SOURCE TO GTID_ONLY = 1', 'CHANGE REPLICATION SOURCE TO GTID_ONLY = 1'])]
    #[TestWith(['mysql-8.4.7', "CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION = 1 FOR CHANNEL 'c'", "CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION = 1 FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-9.0.1', "CHANGE REPLICATION SOURCE TO RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4", "CHANGE REPLICATION SOURCE TO RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4"])]
    #[TestWith(['mysql-9.1.0', "CHANGE REPLICATION SOURCE TO NETWORK_NAMESPACE = 'n'", "CHANGE REPLICATION SOURCE TO NETWORK_NAMESPACE = 'n'"])]
    public function testBindReadsTheSourceFormOnEveryRelease(string $version, string $sql, string $written): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($written)));
    }

    #[TestWith(['mysql-5.7.44', 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a), REPLICATE_DO_DB = ()', 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = ()'])]
    #[TestWith(['mysql-8.0.44', "CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (a.b) FOR CHANNEL 'c'", "CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (`a`.`b`) FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-9.1.0', 'CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((a, b))', 'CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((`a`, `b`))'])]
    public function testBindReadsTheFilterFormOnEveryRelease(string $version, string $sql, string $written): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testBindDiagnosesConflictingCoordinates(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SourceCoordinates->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_LOG_FILE = 'b', RELAY_LOG_POS = 4");
    }

    public function testEffectiveKeepsTheLastAssignmentAndAccumulatesServerIds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO IGNORE_SERVER_IDS = (1), SOURCE_SSL = 1, IGNORE_SERVER_IDS = (2)');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(IgnoredServers::class, $statement->settings[0]);
        $effective = Changes::effective([new SourceFlag(SourceOption::Ssl, true), $statement->settings[0], new SourceFlag(SourceOption::Ssl, false), new IgnoredServers([])]);
        self::assertEquals(['SOURCE_SSL' => new SourceFlag(SourceOption::Ssl, false), 'IGNORE_SERVER_IDS' => $statement->settings[0]], $effective);
    }

    public function testDiagnoseTurnsAnInvalidStructureIntoInvalidSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_SSL = 1');
        self::assertSame($statement, Changes::diagnose(static fn () => $statement, $statement->origin->source));
        $this->expectException(InvalidSql::class);
        Changes::diagnose(static fn () => throw new InvalidStructure('x'), $statement->origin->source);
    }
}

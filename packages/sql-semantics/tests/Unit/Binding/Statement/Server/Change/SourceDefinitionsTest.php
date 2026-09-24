<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Change\SourceDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Replication\Source\SourceFlag;
use SqlSemantics\Model\Configuration\Replication\Source\SourceNumber;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Configuration\Replication\Source\SourceText;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceDefinitions::class)]
#[Medium]
final class SourceDefinitionsTest extends TestCase
{
    public function testReadTypesEveryValueKind(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_BIND = 'b', SOURCE_RETRY_COUNT = 1e2, SOURCE_SSL_VERIFY_SERVER_CERT = 2");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(SourceText::class, $statement->settings[0]);
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_BIND = 'b', SOURCE_RETRY_COUNT = 1e2, SOURCE_SSL_VERIFY_SERVER_CERT = 1", $statement->toString());
    }

    #[TestWith(['REQUIRE_ROW_FORMAT = 2'])]
    #[TestWith(['GTID_ONLY = 1.0'])]
    #[TestWith(['SOURCE_CONNECTION_AUTO_FAILOVER = 3'])]
    public function testReadDiagnosesAValueOutsideTheDomain(string $option): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ReplicationOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO ' . $option);
    }

    public function testFlagReadsTheIntegerPrefix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO REQUIRE_ROW_FORMAT = 1.9, SOURCE_AUTO_POSITION = 0.9');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertEquals([new SourceFlag(SourceOption::RequireRowFormat, true), new SourceFlag(SourceOption::AutoPosition, false)], $statement->settings);
        $port = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_PORT = 2');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $port);
        self::assertInstanceOf(SourceNumber::class, $port->settings[0]);
        self::assertEquals(new SourceFlag(SourceOption::Ssl, true), SourceDefinitions::flag(SourceOption::Ssl, $port->settings[0]->value));
        $this->expectException(InvalidStructure::class);
        SourceDefinitions::flag(SourceOption::GtidOnly, $port->settings[0]->value);
    }

    public function testAccountReadsNamesAndNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = 'a'@'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertEquals(new \SqlSemantics\Model\Configuration\Replication\Source\PrivilegeChecks(new AccountName('a', 'h')), $statement->settings[0]);
        self::assertSame("CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = 'a' @'h'", $statement->toString());
        $null = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = NULL');
        self::assertSame('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = NULL', $null->toString());
    }
}

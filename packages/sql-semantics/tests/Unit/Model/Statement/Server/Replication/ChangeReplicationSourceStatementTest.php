<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\SourceFlag;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeReplicationSourceStatement::class)]
#[Medium]
final class ChangeReplicationSourceStatementTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', "CHANGE MASTER TO MASTER_HOST = 'h', MASTER_PORT = 3306 FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.0.44', "CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h', SOURCE_PORT = 3306 FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-9.1.0', "CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h', SOURCE_PORT = 3306 FOR CHANNEL 'c'"])]
    public function testWithOriginPreservesSettingsAndChannel(string $version, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->settings, $copy->settings);
        self::assertSame('c', $copy->channel);
        self::assertSame($sql, $copy->toString());
        self::assertSame(StatementKind::Change, $statement->kind);
    }

    public function testWithSettingsReplacesTheOptionsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame('CHANGE REPLICATION SOURCE TO SOURCE_SSL = 1', $statement->withSettings([new SourceFlag(SourceOption::Ssl, true)])->toString());
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'", $statement->toString());
    }

    public function testWithChannelReplacesTheChannelImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h' FOR CHANNEL 'c'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'", $statement->withChannel(null)->toString());
        self::assertSame('c', $statement->channel);
    }

    public function testRejectsAnEmptyOptionList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'");
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationSourceStatement($statement->origin, []);
    }

    public function testRejectsAChannelBeforeMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("CHANGE MASTER TO MASTER_HOST = 'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame("CHANGE MASTER TO MASTER_HOST = 'h'", $statement->toString());
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationSourceStatement($statement->origin, $statement->settings, 'c');
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationSourceStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->settings);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowReplicaStatusStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowReplicaStatusStatement::class)]
#[Medium]
final class ShowReplicaStatusStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'SHOW SLAVE STATUS', 'Slave_IO_State', 'Auto_Position'])]
    #[TestWith(['mysql-5.7.44', "SHOW SLAVE STATUS FOR CHANNEL 'c'", 'Slave_IO_State', 'Master_TLS_Version'])]
    #[TestWith(['mysql-8.0.44', "SHOW SLAVE STATUS FOR CHANNEL 'c'", 'Slave_IO_State', 'Network_Namespace'])]
    #[TestWith(['mysql-8.0.44', "SHOW REPLICA STATUS FOR CHANNEL 'c'", 'Replica_IO_State', 'Network_Namespace'])]
    #[TestWith(['mysql-9.1.0', 'SHOW REPLICA STATUS', 'Replica_IO_State', 'Network_Namespace'])]
    public function testResultColumnsFollowTheVocabularyAndRelease(string $version, string $sql, string $first, string $last): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ShowReplicaStatusStatement::class, $statement);
        $names = array_column($statement->resultColumns(), 'name');
        self::assertSame([$first, $last], [$names[0], $names[count($names) - 1]]);
        self::assertSame($sql, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithVocabularyRelabelsTheResultImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('SHOW REPLICA STATUS');
        self::assertInstanceOf(ShowReplicaStatusStatement::class, $statement);
        $changed = $statement->withVocabulary(ReplicationVocabulary::Legacy);
        self::assertNotSame($statement, $changed);
        self::assertSame(ReplicationVocabulary::Current, $statement->vocabulary);
        self::assertSame('SHOW SLAVE STATUS', $changed->toString());
        self::assertSame(['Slave_IO_State', 'Master_Host'], array_slice(array_column($changed->resultColumns(), 'name'), 0, 2));
    }

    public function testWithChannelSelectsAnotherChannelImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW REPLICA STATUS');
        self::assertInstanceOf(ShowReplicaStatusStatement::class, $statement);
        $changed = $statement->withChannel("a'b");
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->channel);
        self::assertSame("SHOW REPLICA STATUS FOR CHANNEL 'a''b'", $changed->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW REPLICA STATUS FOR CHANNEL 'c'");
        self::assertInstanceOf(ShowReplicaStatusStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->vocabulary, $statement->channel], [$copy->vocabulary, $copy->channel]);
    }

    #[TestWith(['mysql-5.7.44', ReplicationVocabulary::Current, null])]
    #[TestWith(['mysql-8.4.7', ReplicationVocabulary::Legacy, null])]
    #[TestWith(['mysql-5.6.51', ReplicationVocabulary::Legacy, 'c'])]
    #[TestWith(['mysql-8.4.7', ReplicationVocabulary::Current, "a\nb"])]
    public function testRejectsFormsTheReleaseCannotSpell(string $version, ReplicationVocabulary $vocabulary, ?string $channel): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SHOW BINARY LOGS');
        $this->expectException(InvalidStructure::class);
        new ShowReplicaStatusStatement($statement->origin, $vocabulary, $channel);
    }
}

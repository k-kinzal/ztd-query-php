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
use SqlSemantics\Model\Statement\Inspection\Replication\ShowReplicasStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowReplicasStatement::class)]
#[Medium]
final class ShowReplicasStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'SHOW SLAVE HOSTS', 'Slave_UUID'])]
    #[TestWith(['mysql-5.7.44', 'SHOW SLAVE HOSTS', 'Slave_UUID'])]
    #[TestWith(['mysql-8.0.44', 'SHOW SLAVE HOSTS', 'Slave_UUID'])]
    #[TestWith(['mysql-8.0.44', 'SHOW REPLICAS', 'Replica_UUID'])]
    #[TestWith(['mysql-9.1.0', 'SHOW REPLICAS', 'Replica_UUID'])]
    public function testResultColumnsFollowTheVocabulary(string $version, string $sql, string $uuid): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ShowReplicasStatement::class, $statement);
        self::assertSame($uuid, $statement->resultColumns()[4]->name);
        self::assertSame($sql, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithVocabularyRelabelsTheResultImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('SHOW SLAVE HOSTS');
        self::assertInstanceOf(ShowReplicasStatement::class, $statement);
        $changed = $statement->withVocabulary(ReplicationVocabulary::Current);
        self::assertNotSame($statement, $changed);
        self::assertSame(ReplicationVocabulary::Legacy, $statement->vocabulary);
        self::assertSame('SHOW REPLICAS', $changed->toString());
        self::assertSame('Source_Id', $changed->resultColumns()[3]->name);
    }

    public function testWithOriginRetainsTheVocabulary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW REPLICAS');
        self::assertInstanceOf(ShowReplicasStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->vocabulary, $copy->vocabulary);
    }

    #[TestWith(['mysql-5.7.44', ReplicationVocabulary::Current])]
    #[TestWith(['mysql-9.0.1', ReplicationVocabulary::Legacy])]
    public function testRejectsAVocabularyTheReleaseCannotSpell(string $version, ReplicationVocabulary $vocabulary): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SHOW BINARY LOGS');
        $this->expectException(InvalidStructure::class);
        new ShowReplicasStatement($statement->origin, $vocabulary);
    }
}

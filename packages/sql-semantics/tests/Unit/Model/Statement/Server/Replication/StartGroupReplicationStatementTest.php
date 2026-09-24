<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Replication\StartGroupReplicationStatement;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StartGroupReplicationStatement::class)]
#[Medium]
final class StartGroupReplicationStatementTest extends TestCase
{
    public function testWithOriginPreservesTheCredentials(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START GROUP_REPLICATION PASSWORD = 'p', USER = 'u'");
        self::assertInstanceOf(StartGroupReplicationStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("START GROUP_REPLICATION PASSWORD = 'p', USER = 'u'", $copy->toString());
    }

    public function testWithCredentialsReplacesTheCredentialsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START GROUP_REPLICATION USER = 'u'");
        self::assertInstanceOf(StartGroupReplicationStatement::class, $statement);
        self::assertSame('START GROUP_REPLICATION', $statement->withCredentials([])->toString());
        self::assertCount(1, $statement->credentials);
    }

    public function testRejectsAPluginDirectory(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $group = $binder->bind('START GROUP_REPLICATION');
        $replica = $binder->bind("START REPLICA PLUGIN_DIR = 'd'");
        self::assertInstanceOf(StartReplicaStatement::class, $replica);
        $this->expectException(InvalidStructure::class);
        new StartGroupReplicationStatement($group->origin, $replica->credentials);
    }

    public function testRejectsCredentialsBeforeMySql8(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('START GROUP_REPLICATION');
        $current = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("START GROUP_REPLICATION USER = 'u'");
        self::assertInstanceOf(StartGroupReplicationStatement::class, $current);
        $this->expectException(InvalidStructure::class);
        new StartGroupReplicationStatement($legacy->origin, $current->credentials);
    }
}

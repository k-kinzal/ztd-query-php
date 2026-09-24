<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\TlsChannel;
use SqlSemantics\Model\Statement\Server\Administration\ReloadTlsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReloadTlsStatement::class)]
#[Medium]
final class ReloadTlsStatementTest extends TestCase
{
    public function testWithOriginPreservesChannelAndPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_admin NO ROLLBACK ON ERROR');
        self::assertInstanceOf(ReloadTlsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame(TlsChannel::Admin, $copy->channel);
        self::assertFalse($copy->rollbackOnError);
        self::assertSame('ALTER INSTANCE RELOAD TLS FOR CHANNEL `mysql_admin` NO ROLLBACK ON ERROR', $copy->toString());
    }

    public function testWithChannelSelectsTheContextImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE RELOAD TLS');
        self::assertInstanceOf(ReloadTlsStatement::class, $statement);
        self::assertSame('ALTER INSTANCE RELOAD TLS FOR CHANNEL `mysql_admin`', $statement->withChannel(TlsChannel::Admin)->toString());
        self::assertSame(TlsChannel::Main, $statement->channel);
    }

    public function testWithRollbackOnErrorChangesThePolicyImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE RELOAD TLS');
        self::assertInstanceOf(ReloadTlsStatement::class, $statement);
        self::assertSame('ALTER INSTANCE RELOAD TLS NO ROLLBACK ON ERROR', $statement->withRollbackOnError(false)->toString());
        self::assertTrue($statement->rollbackOnError);
    }

    public function testRejectsALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        $this->expectException(InvalidStructure::class);
        new ReloadTlsStatement($statement->origin);
    }

    public function testDefaultsToTheMainChannelWithRollback(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('ALTER INSTANCE RELOAD TLS');
        $default = new ReloadTlsStatement($statement->origin);
        self::assertSame(TlsChannel::Main, $default->channel);
        self::assertTrue($default->rollbackOnError);
    }
}

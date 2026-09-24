<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Replication\StopGroupReplicationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StopGroupReplicationStatement::class)]
#[Medium]
final class StopGroupReplicationStatementTest extends TestCase
{
    public function testWithOriginKeepsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('STOP GROUP_REPLICATION');
        self::assertInstanceOf(StopGroupReplicationStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('STOP GROUP_REPLICATION', $copy->toString());
    }

    public function testRejectsMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('STOP SLAVE');
        $this->expectException(InvalidStructure::class);
        new StopGroupReplicationStatement($statement->origin);
    }
}

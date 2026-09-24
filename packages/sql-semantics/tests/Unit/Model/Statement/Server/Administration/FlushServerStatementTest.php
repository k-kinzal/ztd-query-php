<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FlushServerStatement::class)]
#[Medium]
final class FlushServerStatementTest extends TestCase
{
    public function testWithOriginPreservesTheTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("FLUSH LOCAL RELAY LOGS FOR CHANNEL 'c', STATUS");
        self::assertInstanceOf(FlushServerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("FLUSH NO_WRITE_TO_BINLOG RELAY LOGS FOR CHANNEL 'c', STATUS", $copy->toString());
    }

    public function testWithTargetsReplacesTheOptionsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('FLUSH LOGS');
        self::assertInstanceOf(FlushServerStatement::class, $statement);
        self::assertSame('FLUSH PRIVILEGES, RELAY LOGS', $statement->withTargets([ServerFlush::Privileges, new RelayLogFlush()])->toString());
        self::assertSame([ServerFlush::Logs], $statement->targets);
    }

    public function testWithBinlogChangesThePolicyImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('FLUSH LOGS');
        self::assertInstanceOf(FlushServerStatement::class, $statement);
        self::assertSame(BinlogPolicy::Omit, $statement->withBinlog(BinlogPolicy::Omit)->binlog);
        self::assertSame(BinlogPolicy::Write, $statement->binlog);
    }

    public function testRejectsAnOptionMissingFromTheRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('FLUSH LOGS');
        $this->expectException(InvalidStructure::class);
        new FlushServerStatement($statement->origin, [ServerFlush::QueryCache]);
    }

    public function testRejectsAnEmptyOptionList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('FLUSH LOGS');
        $this->expectException(InvalidStructure::class);
        new FlushServerStatement($statement->origin, []);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\Source\SourceNumber;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceNumber::class)]
#[Medium]
final class SourceNumberTest extends TestCase
{
    public function testOptionReturnsTheAssignedOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD = 1.5, SOURCE_DELAY = 2147483647');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(SourceNumber::class, $statement->settings[0]);
        self::assertSame(SourceOption::HeartbeatPeriod, $statement->settings[0]->option());
        self::assertSame('1.5', $statement->settings[0]->value->text);
    }

    public function testRejectsADelayOutOfRange(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_DELAY = 2147483648');
    }

    public function testRejectsAHeartbeatPeriodOutOfRange(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD = 4294968');
    }

    public function testRejectsATextOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_PORT = 1');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertInstanceOf(SourceNumber::class, $statement->settings[0]);
        $this->expectException(InvalidStructure::class);
        new SourceNumber(SourceOption::Host, $statement->settings[0]->value);
    }
}

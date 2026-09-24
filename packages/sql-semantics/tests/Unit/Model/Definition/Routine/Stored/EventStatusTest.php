<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventStatus::class)]
#[Medium]
final class EventStatusTest extends TestCase
{
    #[TestWith(['ENABLE', EventStatus::Enabled])]
    #[TestWith(['DISABLE', EventStatus::Disabled])]
    #[TestWith(['DISABLE ON SLAVE', EventStatus::DisabledOnReplica])]
    #[TestWith(['DISABLE ON REPLICA', EventStatus::DisabledOnReplica])]
    public function testTreatsReplicaAndSlaveAsOneStatus(string $spelling, EventStatus $status): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP ' . $spelling . ' DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame($status, $statement->status);
    }
}

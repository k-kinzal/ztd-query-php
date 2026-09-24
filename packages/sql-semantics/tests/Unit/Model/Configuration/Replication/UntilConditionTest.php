<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\UntilCondition;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UntilCondition::class)]
#[Medium]
final class UntilConditionTest extends TestCase
{
    public function testEveryStopPointIsAnUntilCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START REPLICA UNTIL SQL_AFTER_MTS_GAPS');
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(UntilCondition::class, $statement->until);
    }
}

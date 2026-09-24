<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\GapsClosed;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GapsClosed::class)]
#[Medium]
final class GapsClosedTest extends TestCase
{
    public function testBindsSqlAfterMtsGaps(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START REPLICA SQL_THREAD UNTIL SQL_AFTER_MTS_GAPS');
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(GapsClosed::class, $statement->until);
    }
}

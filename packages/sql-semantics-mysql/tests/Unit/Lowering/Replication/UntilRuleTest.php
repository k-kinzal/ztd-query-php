<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\UntilRule;

#[CoversClass(UntilRule::class)]
#[Medium]
final class UntilRuleTest extends TestCase
{
    public function testUntilLowersAnAbsentAndAWrittenClause(): void
    {
        self::assertSame('START SLAVE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('start slave')->toString());
        self::assertSame("START SLAVE UNTIL SQL_BEFORE_GTIDS = 'g'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("start slave until sql_before_gtids = 'g'")->toString());
    }

    public function testConditionsKeepsThePositionsInOrder(): void
    {
        self::assertSame("START REPLICA UNTIL RELAY_LOG_POS = 4, RELAY_LOG_FILE = 'r', SOURCE_LOG_POS = 5", (new Semantics(Dialect::MySql))->analyze("start replica until relay_log_pos = 4, relay_log_file = 'r', source_log_pos = 5")->toString());
    }
}

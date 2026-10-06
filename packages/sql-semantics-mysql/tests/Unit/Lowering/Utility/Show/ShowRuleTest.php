<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ShowRule;

#[CoversClass(ShowRule::class)]
#[Medium]
final class ShowRuleTest extends TestCase
{
    public function testStatementLowersTheSessionStatements(): void
    {
        self::assertSame('SHOW COUNT(*) ERRORS', (new Semantics(Dialect::MySql))->analyze('show count(*) errors')->toString());
        self::assertSame('SHOW PARSE_TREE SELECT 1', (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze('show parse_tree select 1')->toString());
    }

    public function testReplicationLowersEverySpelling(): void
    {
        self::assertSame('SHOW SLAVE HOSTS', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('show slave hosts')->toString());
        self::assertSame("SHOW REPLICA STATUS FOR CHANNEL 'c'", (new Semantics(Dialect::MySql))->analyze('show replica status for channel \'c\'')->toString());
        self::assertSame('SHOW BINARY LOG STATUS', (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze('show binary log status')->toString());
    }
}

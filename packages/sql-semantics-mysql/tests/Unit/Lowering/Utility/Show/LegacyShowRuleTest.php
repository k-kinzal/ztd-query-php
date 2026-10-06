<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\LegacyShowRule;

#[CoversClass(LegacyShowRule::class)]
#[Medium]
final class LegacyShowRuleTest extends TestCase
{
    public function testStatementLowersShowParam(): void
    {
        self::assertSame('SHOW SLAVE STATUS', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show slave status')->toString());
        self::assertSame("SHOW SLAVE STATUS FOR CHANNEL 'c'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show slave status for channel \'c\'')->toString());
    }

    public function testSessionLowersTheSessionStatements(): void
    {
        self::assertSame('SHOW RELAYLOG EVENTS LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('show relaylog events limit 1')->toString());
        self::assertSame('SHOW MASTER STATUS', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show master status')->toString());
    }

    public function testBinaryLogsLowersBothKeywords(): void
    {
        self::assertSame('SHOW BINARY LOGS', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('show binary logs')->toString());
    }
}

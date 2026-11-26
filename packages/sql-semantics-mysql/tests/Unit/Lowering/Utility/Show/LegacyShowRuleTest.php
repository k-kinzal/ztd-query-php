<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\LegacyShowRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LegacyShowRule::class)]
#[Medium]
final class LegacyShowRuleTest extends TestCase
{
    public function testStatementLowersShowParam(): void
    {
        self::assertSame('SHOW SLAVE STATUS', (new Inspection('mysql-5.6.51'))->inspect('show slave status')->toString());
        self::assertSame("SHOW SLAVE STATUS FOR CHANNEL 'c'", (new Inspection('mysql-5.7.44'))->inspect('show slave status for channel \'c\'')->toString());
    }

    public function testSessionLowersTheSessionStatements(): void
    {
        self::assertSame('SHOW RELAYLOG EVENTS LIMIT 1', (new Inspection('mysql-5.6.51'))->inspect('show relaylog events limit 1')->toString());
        self::assertSame('SHOW MASTER STATUS', (new Inspection('mysql-5.7.44'))->inspect('show master status')->toString());
    }

    public function testBinaryLogsLowersBothKeywords(): void
    {
        self::assertSame('SHOW BINARY LOGS', (new Inspection('mysql-5.7.44'))->inspect('show binary logs')->toString());
    }
}

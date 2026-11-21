<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ShowRule;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(ShowRule::class)]
#[Medium]
final class ShowRuleTest extends TestCase
{
    public function testStatementLowersTheSessionStatements(): void
    {
        self::assertSame('SHOW COUNT(*) ERRORS', (new Semantics())->inspect('show count(*) errors')->toString());
        self::assertSame('SHOW PARSE_TREE SELECT 1', (new Semantics('mysql-8.1.0'))->inspect('show parse_tree select 1')->toString());
    }

    public function testReplicationLowersEverySpelling(): void
    {
        self::assertSame('SHOW SLAVE HOSTS', (new Semantics('mysql-8.0.44'))->inspect('show slave hosts')->toString());
        self::assertSame("SHOW REPLICA STATUS FOR CHANNEL 'c'", (new Semantics())->inspect('show replica status for channel \'c\'')->toString());
        self::assertSame('SHOW BINARY LOG STATUS', (new Semantics('mysql-8.2.0'))->inspect('show binary log status')->toString());
    }
}

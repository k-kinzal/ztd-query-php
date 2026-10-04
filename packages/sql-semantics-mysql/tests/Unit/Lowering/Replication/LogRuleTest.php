<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\LogRule;

#[CoversClass(LogRule::class)]
#[Medium]
final class LogRuleTest extends TestCase
{
    public function testPurgeLowersBothForms(): void
    {
        self::assertSame("PURGE BINARY LOGS TO 'f'", (new Semantics(Dialect::MySql))->analyze("purge binary logs to 'f'")->toString());
        self::assertSame('PURGE BINARY LOGS BEFORE NOW()', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('purge master logs before now()')->toString());
    }

    public function testBinlogLowersTheEvents(): void
    {
        self::assertSame("BINLOG 'e'", (new Semantics(Dialect::MySql))->analyze("binlog 'e'")->toString());
    }

    public function testGroupLowersStartAndStop(): void
    {
        self::assertSame('STOP GROUP_REPLICATION', (new Semantics(Dialect::MySql))->analyze('stop group_replication')->toString());
        self::assertSame('START GROUP_REPLICATION', (new Semantics(Dialect::MySql))->analyze('start group_replication')->toString());
    }

    public function testStartLowersTheOptionsInOrder(): void
    {
        self::assertSame("START GROUP_REPLICATION PASSWORD = 'p', USER = 'u', USER = 'v'", (new Semantics(Dialect::MySql))->analyze("start group_replication password = 'p', user = 'u', user = 'v'")->toString());
    }

    public function testOptionLowersEachCredential(): void
    {
        self::assertSame("START GROUP_REPLICATION DEFAULT_AUTH = 'a'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("start group_replication default_auth = 'a'")->toString());
    }
}

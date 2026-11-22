<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Replication\LogRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LogRule::class)]
#[Medium]
final class LogRuleTest extends TestCase
{
    public function testPurgeLowersBothForms(): void
    {
        self::assertSame("PURGE BINARY LOGS TO 'f'", (new Inspection())->inspect("purge binary logs to 'f'")->toString());
        self::assertSame('PURGE BINARY LOGS BEFORE NOW()', (new Inspection('mysql-8.0.44'))->inspect('purge master logs before now()')->toString());
    }

    public function testBinlogLowersTheEvents(): void
    {
        self::assertSame("BINLOG 'e'", (new Inspection())->inspect("binlog 'e'")->toString());
    }

    public function testGroupLowersStartAndStop(): void
    {
        self::assertSame('STOP GROUP_REPLICATION', (new Inspection())->inspect('stop group_replication')->toString());
        self::assertSame('START GROUP_REPLICATION', (new Inspection())->inspect('start group_replication')->toString());
    }

    public function testStartLowersTheOptionsInOrder(): void
    {
        self::assertSame("START GROUP_REPLICATION PASSWORD = 'p', USER = 'u', USER = 'v'", (new Inspection())->inspect("start group_replication password = 'p', user = 'u', user = 'v'")->toString());
    }

    public function testOptionLowersEachCredential(): void
    {
        self::assertSame("START GROUP_REPLICATION DEFAULT_AUTH = 'a'", (new Inspection('mysql-8.0.44'))->inspect("start group_replication default_auth = 'a'")->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\LockRule;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(LockRule::class)]
#[Medium]
final class LockRuleTest extends TestCase
{
    public function testStatementLowersInstanceLocks(): void
    {
        self::assertSame('UNLOCK INSTANCE', (new Semantics('mysql-8.0.44'))->inspect('unlock instance')->toString());
    }

    public function testTablesLowersTheList(): void
    {
        self::assertSame('LOCK TABLES t WRITE, u READ', (new Semantics())->inspect('lock tables t write, u read')->toString());
    }

    public function testLockLowersTheAlias(): void
    {
        self::assertSame('LOCK TABLES t AS `x y` READ LOCAL', (new Semantics('mysql-5.6.51'))->inspect('lock table t as `x y` read local')->toString());
    }

    public function testUnlockLowersTableAndTables(): void
    {
        self::assertSame('UNLOCK TABLES', (new Semantics())->inspect('unlock table')->toString());
    }
}

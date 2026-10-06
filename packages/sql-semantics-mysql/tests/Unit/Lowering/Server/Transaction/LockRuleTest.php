<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\LockRule;

#[CoversClass(LockRule::class)]
#[Medium]
final class LockRuleTest extends TestCase
{
    public function testStatementLowersInstanceLocks(): void
    {
        self::assertSame('UNLOCK INSTANCE', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('unlock instance')->toString());
    }

    public function testTablesLowersTheList(): void
    {
        self::assertSame('LOCK TABLES t WRITE, u READ', (new Semantics(Dialect::MySql))->analyze('lock tables t write, u read')->toString());
    }

    public function testLockLowersTheAlias(): void
    {
        self::assertSame('LOCK TABLES t AS `x y` READ LOCAL', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('lock table t as `x y` read local')->toString());
    }

    public function testUnlockLowersTableAndTables(): void
    {
        self::assertSame('UNLOCK TABLES', (new Semantics(Dialect::MySql))->analyze('unlock table')->toString());
    }
}

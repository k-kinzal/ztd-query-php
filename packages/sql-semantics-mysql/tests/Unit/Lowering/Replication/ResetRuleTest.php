<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\ResetRule;

#[CoversClass(ResetRule::class)]
#[Medium]
final class ResetRuleTest extends TestCase
{
    public function testStatementLowersBothForms(): void
    {
        self::assertSame('RESET PERSIST', (new Semantics(Dialect::MySql))->analyze('reset persist')->toString());
        self::assertSame('RESET REPLICA, REPLICA ALL', (new Semantics(Dialect::MySql))->analyze('reset replica, replica all')->toString());
    }

    public function testTargetReadsTheDeprecatedSpellingsAsSynonyms(): void
    {
        self::assertSame('RESET REPLICA, BINARY LOGS AND GTIDS', (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze('reset slave, master')->toString());
        self::assertSame('RESET REPLICA, MASTER', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('reset slave, master')->toString());
    }

    public function testFirstLowersTheFileNumber(): void
    {
        self::assertSame('RESET BINARY LOGS AND GTIDS TO 3', (new Semantics(Dialect::MySql))->analyze('reset binary logs and gtids to 3')->toString());
    }

    public function testPersistLowersEveryVariableForm(): void
    {
        self::assertSame('RESET PERSIST IF EXISTS c.v', (new Semantics(Dialect::MySql))->analyze('reset persist if exists c.v')->toString());
        self::assertSame('RESET PERSIST v', (new Semantics(Dialect::MySql))->analyze('reset persist v')->toString());
    }
}

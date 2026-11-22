<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Replication\ResetRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ResetRule::class)]
#[Medium]
final class ResetRuleTest extends TestCase
{
    public function testStatementLowersBothForms(): void
    {
        self::assertSame('RESET PERSIST', (new Inspection())->inspect('reset persist')->toString());
        self::assertSame('RESET REPLICA, REPLICA ALL', (new Inspection())->inspect('reset replica, replica all')->toString());
    }

    public function testTargetReadsTheDeprecatedSpellingsAsSynonyms(): void
    {
        self::assertSame('RESET REPLICA, BINARY LOGS AND GTIDS', (new Inspection('mysql-8.3.0'))->inspect('reset slave, master')->toString());
        self::assertSame('RESET REPLICA, MASTER', (new Inspection('mysql-8.0.44'))->inspect('reset slave, master')->toString());
    }

    public function testFirstLowersTheFileNumber(): void
    {
        self::assertSame('RESET BINARY LOGS AND GTIDS TO 3', (new Inspection())->inspect('reset binary logs and gtids to 3')->toString());
    }

    public function testPersistLowersEveryVariableForm(): void
    {
        self::assertSame('RESET PERSIST IF EXISTS c.v', (new Inspection())->inspect('reset persist if exists c.v')->toString());
        self::assertSame('RESET PERSIST v', (new Inspection())->inspect('reset persist v')->toString());
    }
}

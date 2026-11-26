<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\PasswordRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PasswordRule::class)]
#[Medium]
final class PasswordRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame("SET PASSWORD TO RANDOM REPLACE 'o'", (new Inspection())->inspect("set password to random replace 'o'")->toString());
        self::assertSame("SET PASSWORD FOR u = PASSWORD('x')", (new Inspection('mysql-5.7.44'))->inspect("set password for u = password('x')")->toString());
        self::assertSame("SET PASSWORD = 'h'", (new Inspection('mysql-5.6.51'))->inspect("set password = 'h'")->toString());
    }

    public function testPasswordLowersEveryOperand(): void
    {
        self::assertSame("SET PASSWORD = OLD_PASSWORD('x')", (new Inspection('mysql-5.6.51'))->inspect("set password = old_password('x')")->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\PasswordRule;

#[CoversClass(PasswordRule::class)]
#[Medium]
final class PasswordRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame("SET PASSWORD TO RANDOM REPLACE 'o'", (new Semantics(Dialect::MySql))->analyze("set password to random replace 'o'")->toString());
        self::assertSame("SET PASSWORD FOR u = PASSWORD('x')", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("set password for u = password('x')")->toString());
        self::assertSame("SET PASSWORD = 'h'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set password = 'h'")->toString());
    }

    public function testPasswordLowersEveryOperand(): void
    {
        self::assertSame("SET PASSWORD = OLD_PASSWORD('x')", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set password = old_password('x')")->toString());
    }
}

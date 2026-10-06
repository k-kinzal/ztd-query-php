<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Password;

#[CoversClass(Password::class)]
#[Medium]
final class PasswordTest extends TestCase
{
    public function testRenderWritesTheFunction(): void
    {
        self::assertSame("SET PASSWORD = PASSWORD('x')", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("set password = password('x')")->toString());
    }

    public function testRenderWritesABareString(): void
    {
        self::assertSame("SET PASSWORD = 'x'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("set password = 'x'")->toString());
    }
}

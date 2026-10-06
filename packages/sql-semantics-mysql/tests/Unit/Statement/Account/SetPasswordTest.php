<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;

#[CoversClass(SetPassword::class)]
#[Medium]
final class SetPasswordTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze("SET PASSWORD = 'x'")->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("SET PASSWORD FOR u TO RANDOM REPLACE 'old' RETAIN CURRENT PASSWORD", (new Semantics(Dialect::MySql))->analyze("set password for u to random replace 'old' retain current password")->toString());
        self::assertSame("SET PASSWORD FOR u = OLD_PASSWORD('x')", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set password for u := old_password('x')")->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SetPassword::class)]
#[Medium]
final class SetPasswordTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect("SET PASSWORD = 'x'")->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("SET PASSWORD FOR u TO RANDOM REPLACE 'old' RETAIN CURRENT PASSWORD", (new Inspection())->inspect("set password for u to random replace 'old' retain current password")->toString());
        self::assertSame("SET PASSWORD FOR u = OLD_PASSWORD('x')", (new Inspection('mysql-5.6.51'))->inspect("set password for u := old_password('x')")->toString());
    }
}

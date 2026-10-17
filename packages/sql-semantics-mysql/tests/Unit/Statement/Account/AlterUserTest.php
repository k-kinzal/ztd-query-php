<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;

#[CoversClass(AlterUser::class)]
#[Medium]
final class AlterUserTest extends TestCase
{
    public function testDeriveStatementAcceptsPluginAuthenticationStrings(): void
    {
        $hash = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("ALTER USER u IDENTIFIED WITH mysql_native_password AS '*0123456789012345678901234567890123456789'");

        self::assertSame([], $hash->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("ALTER USER IF EXISTS u DISCARD OLD PASSWORD, v REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 1 PASSWORD REQUIRE CURRENT OPTIONAL ATTRIBUTE '{}'", (new Semantics(Dialect::MySql))->analyze("alter user if exists u discard old password, v require none with max_queries_per_hour 1 password require current optional attribute '{}'")->toString());
    }
}

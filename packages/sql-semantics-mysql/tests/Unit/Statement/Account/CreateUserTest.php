<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute;

#[CoversClass(CreateUser::class)]
#[Medium]
final class CreateUserTest extends TestCase
{
    public function testDeriveStatementReportsTheProblemsOfTheClauses(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE USER u REQUIRE ISSUER 'a' ISSUER 'b' PASSWORD EXPIRE INTERVAL 0 DAY ATTRIBUTE 'x'");

        self::assertInstanceOf(RepeatedTlsAttribute::class, $create->facts->diagnostics[0]);
        self::assertInstanceOf(NumberOutOfRange::class, $create->facts->diagnostics[1]);
        self::assertInstanceOf(InvalidUserAttribute::class, $create->facts->diagnostics[2]);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("CREATE USER IF NOT EXISTS a IDENTIFIED BY 'x' AND IDENTIFIED WITH p, b DEFAULT ROLE r REQUIRE X509 WITH MAX_USER_CONNECTIONS 2 ACCOUNT LOCK PASSWORD HISTORY DEFAULT COMMENT 'c'", (new Semantics(Dialect::MySql))->analyze("create user if not exists a identified by 'x' and identified with p, b default role r require x509 with max_user_connections 2 account lock password history default comment 'c'")->toString());
    }
}

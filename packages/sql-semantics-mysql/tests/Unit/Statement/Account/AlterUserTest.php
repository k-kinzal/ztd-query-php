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

        self::assertSame([], $hash->diagnostics());
    }

    public function testDeriveStatementPublishesAConditionForTheRetainedLegacyHash(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("ALTER USER u IDENTIFIED BY PASSWORD 'hash'");
        self::assertInstanceOf(AlterUser::class, $statement);
        $warning = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warning);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash::class, $warning->problem);
        self::assertTrue($warning->aborts);
        self::assertSame(36, $statement->statementInformation()->warningSubject($warning)?->source()?->offset);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("ALTER USER IF EXISTS u DISCARD OLD PASSWORD, v REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 1 PASSWORD REQUIRE CURRENT OPTIONAL ATTRIBUTE '{}'", (new Semantics(Dialect::MySql))->analyze("alter user if exists u discard old password, v require none with max_queries_per_hour 1 password require current optional attribute '{}'")->toString());
    }
}

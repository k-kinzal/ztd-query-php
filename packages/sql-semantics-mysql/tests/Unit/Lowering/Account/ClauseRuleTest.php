<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\ClauseRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ClauseRule::class)]
#[Medium]
final class ClauseRuleTest extends TestCase
{
    public function testTlsLowersEveryRequirement(): void
    {
        self::assertSame('CREATE USER u REQUIRE X509', (new Inspection())->inspect('create user u require x509')->toString());
        self::assertSame('CREATE USER u REQUIRE NONE', (new Inspection())->inspect('create user u require none')->toString());
    }

    public function testConditionsSkipsTheOptionalAnd(): void
    {
        self::assertSame("CREATE USER u REQUIRE SUBJECT 's' AND ISSUER 'i' AND CIPHER 'c'", (new Inspection())->inspect("create user u require subject 's' and issuer 'i' cipher 'c'")->toString());
    }

    public function testLimitsLowersTheWithClause(): void
    {
        self::assertSame('CREATE USER u WITH MAX_QUERIES_PER_HOUR 1 MAX_UPDATES_PER_HOUR 2', (new Inspection())->inspect('create user u with max_queries_per_hour 1 max_updates_per_hour 2')->toString());
    }

    public function testLimitLowersEveryLimit(): void
    {
        self::assertSame('GRANT USAGE ON *.* TO u WITH MAX_CONNECTIONS_PER_HOUR 1 MAX_USER_CONNECTIONS 2', (new Inspection('mysql-5.6.51'))->inspect('grant usage on *.* to u with max_connections_per_hour 1 max_user_connections 2')->toString());
    }

    public function testGrantOptionsLowersBothGenerations(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u WITH GRANT OPTION', (new Inspection())->inspect('grant select on *.* to u with grant option')->toString());
        self::assertSame('GRANT SELECT ON *.* TO u WITH MAX_QUERIES_PER_HOUR 1 GRANT OPTION', (new Inspection('mysql-5.7.44'))->inspect('grant select on *.* to u with max_queries_per_hour 1 grant option')->toString());
    }

    public function testGrantOptionLowersTheProxyOption(): void
    {
        self::assertSame('GRANT PROXY ON a TO b WITH GRANT OPTION', (new Inspection('mysql-5.7.44'))->inspect('grant proxy on a to b with grant option')->toString());
    }

    public function testOptionsLowersTheOptionList(): void
    {
        self::assertSame('ALTER USER u ACCOUNT UNLOCK PASSWORD EXPIRE DEFAULT', (new Inspection())->inspect('alter user u account unlock password expire default')->toString());
    }

    public function testOptionLowersNumberedOptions(): void
    {
        self::assertSame('CREATE USER u PASSWORD REUSE INTERVAL 5 DAY PASSWORD HISTORY 2', (new Inspection())->inspect('create user u password reuse interval 5 day password history 2')->toString());
    }

    public function testExpireLowersTheLegacyExpiry(): void
    {
        self::assertSame('CREATE USER u PASSWORD EXPIRE NEVER PASSWORD EXPIRE', (new Inspection('mysql-5.7.44'))->inspect('create user u password expire never password expire')->toString());
    }

    public function testCommentLowersTheAttribute(): void
    {
        self::assertSame("CREATE USER u ATTRIBUTE '{}'", (new Inspection())->inspect("create user u attribute '{}'")->toString());
    }

    public function testStringDecodesTheToken(): void
    {
        self::assertSame("CREATE USER u REQUIRE ISSUER 'it''s'", (new Inspection())->inspect('create user u require issuer \'it\\\'s\'')->toString());
    }

    public function testSpineFlattensTheOptionList(): void
    {
        self::assertSame('ALTER USER u PASSWORD REQUIRE CURRENT PASSWORD REQUIRE CURRENT DEFAULT ACCOUNT LOCK', (new Inspection())->inspect('alter user u password require current password require current default account lock')->toString());
    }
}

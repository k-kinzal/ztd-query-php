<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Proxies;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Proxies::class)]
#[Small]
final class ProxiesTest extends TestCase
{
    public function testGrantAllowsTheCurrentAccount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('GRANT PROXY ON root TO u WITH GRANT OPTION');
        $result = $session->query('SHOW GRANTS FOR u')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%` WITH GRANT OPTION'], ['GRANT PROXY ON `root`@`%` TO `u`@`%` WITH GRANT OPTION']], $result->rows);
    }

    public function testGrantReplacesTheProxyOptionWithoutRevokingTheGlobalOption(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('GRANT PROXY ON root TO u WITH GRANT OPTION');
        $session->query('GRANT PROXY ON root TO u');
        $result = $session->query('SHOW GRANTS FOR u')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%` WITH GRANT OPTION'], ['GRANT PROXY ON `root`@`%` TO `u`@`%`']], $result->rows);
    }

    public function testGrantCreatesLegacyAccounts(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("GRANT PROXY ON root TO u IDENTIFIED BY 'x'");
        $result = $session->query('SHOW GRANTS FOR u')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(["GRANT PROXY ON 'root'@'%' TO 'u'@'%'"], $result->rows[1]);
    }

    public function testGrantChecksAllAccountsBeforeApplyingAnyGrant(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->run('GRANT PROXY ON root TO u, absent_proxy');

        self::assertSame([], $session->instance->accounts->find(new Identity('u', '%'))?->grants->proxies);
        self::assertSame(1410, $session->diagnostics->conditions[0][1]);
    }

    public function testRevokePreservesTheGlobalGrantOption(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('GRANT PROXY ON root TO u WITH GRANT OPTION');
        $session->query('REVOKE PROXY ON root FROM u');
        $result = $session->query('SHOW GRANTS FOR u')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['GRANT USAGE ON *.* TO `u`@`%` WITH GRANT OPTION']], $result->rows);
    }

    public function testRevokeWarnsOfAnAbsentGrantWithIfExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('REVOKE IF EXISTS PROXY ON root FROM u');

        self::assertSame([['Warning', 1141, "There is no such grant defined for user 'u' on host '%'"]], $session->diagnostics->conditions);
    }

    public function testRevokeChecksAuthorityBeforeIgnoringUnknownUsers(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1698);

        $session->query('REVOKE PROXY ON nobody FROM nobody IGNORE UNKNOWN USER');
    }

    public function testGrantStoresCurrentUserAsTheEmptyMappingAndRevokeFindsIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE USER u');
        $session->query('GRANT PROXY ON CURRENT_USER TO u');
        $result = $session->query('SHOW GRANTS FOR u')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['GRANT PROXY ON ``@`` TO `u`@`%`'], $result->rows[1]);

        $session->query('REVOKE PROXY ON CURRENT_USER FROM u');

        self::assertSame([], $session->instance->accounts->find(new Identity('u', '%'))?->grants->proxies);
    }

    public function testCheckAllowsDelegatedAuthority(): void
    {
        $instance = new Instance();
        $root = $instance->connect();
        $root->query('CREATE USER u');
        $root->query('GRANT PROXY ON root TO u WITH GRANT OPTION');
        $user = $instance->connect('u');
        (new Proxies())->check(new Identity('root', '%'), $user);
        $user->query('GRANT PROXY ON root TO root');

        self::assertCount(1, $instance->accounts->find(new Identity('root', '%'))?->grants->proxies ?? []);
    }
}

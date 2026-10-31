<?php

declare(strict_types=1);

namespace Tests\Unit\System\Privilege;

use MySqlMemory\Account\Identity;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Privilege\Users;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Users::class)]
#[Small]
final class UsersTest extends TestCase
{
    public function testRowsListsTheAccountsWithTheirPrivilegesAndPolicies(): void
    {
        $s = (new Instance())->connect();
        $s->query("CREATE USER 'u'@'h%' REQUIRE SUBJECT 'sub' AND CIPHER 'ciph' WITH MAX_QUERIES_PER_HOUR 5 PASSWORD EXPIRE INTERVAL 30 DAY PASSWORD HISTORY 3 PASSWORD REUSE INTERVAL 10 DAY PASSWORD REQUIRE CURRENT ACCOUNT LOCK ATTRIBUTE '{\"a\":1}'");

        $result1 = $s->query("SELECT Host, User, Select_priv, ssl_type, ssl_cipher, x509_issuer, x509_subject, max_questions, plugin, password_expired, password_lifetime, account_locked, Password_reuse_history, Password_reuse_time, Password_require_current, User_attributes FROM mysql.user WHERE User = 'u'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['h%', 'u', 'N', 'SPECIFIED', 'ciph', '', 'sub', '5', 'caching_sha2_password', 'N', '30', 'Y', '3', '10', 'Y', '{"metadata": {"a": 1}}']], $result1->rows);
        $result2 = $s->query("SELECT Host, User, Select_priv, Grant_priv FROM mysql.user WHERE User IN ('root', 'mysql.infoschema') LIMIT 2")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['%', 'root', 'Y', 'Y'], ['localhost', 'mysql.infoschema', 'Y', 'N']], $result2->rows);
    }

    public function testAttributesWritesTheMetadataAndThePasswordLocking(): void
    {
        $s = (new Instance())->connect();
        $s->query("CREATE USER 'u'@'%' FAILED_LOGIN_ATTEMPTS 3 PASSWORD_LOCK_TIME 2");
        $account = $s->instance->accounts->find(new Identity('u', '%'));
        self::assertNotNull($account);

        self::assertSame('{"Password_locking": {"failed_login_attempts": 3, "password_lock_time_days": 2}}', Users::attributes($account));
    }

    public function testRowsKeepsTheStringOfMySqlNativePasswordInThePasswordColumnOfMySql56(): void
    {
        $s = (new Instance('5.6.51'))->connect();
        $s->query("CREATE USER u IDENTIFIED BY 'p'");

        $rows = $s->query("SELECT Password, authentication_string, plugin FROM mysql.user WHERE User = 'u'")[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['*7B9EBEED26AA52ED10C0F549FA863F13C39E0209', '', 'mysql_native_password']], $rows->rows);
    }
}

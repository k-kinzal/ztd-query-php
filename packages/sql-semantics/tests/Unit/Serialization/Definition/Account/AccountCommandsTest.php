<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Account\AccountCommands;

#[CoversClass(AccountCommands::class)]
#[Medium]
final class AccountCommandsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(AccountCommands::write($statement));
    }

    #[TestWith(['mysql-8.0.44', 'CREATE ROLE IF NOT EXISTS r, s@h', "CREATE ROLE IF NOT EXISTS 'r', 's'@'h'"])]
    #[TestWith(['mysql-5.6.51', 'RENAME USER a TO b, CURRENT_USER() TO c@h', "RENAME USER 'a' TO 'b', CURRENT_USER TO 'c'@'h'"])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a DEFAULT ROLE NONE', "ALTER USER 'a' DEFAULT ROLE NONE"])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a DEFAULT ROLE r, s', "ALTER USER 'a' DEFAULT ROLE 'r', 's'"])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a 2 FACTOR INITIATE REGISTRATION', "ALTER USER 'a' 2 FACTOR INITIATE REGISTRATION"])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a 3 FACTOR UNREGISTER', "ALTER USER 'a' 3 FACTOR UNREGISTER"])]
    #[TestWith(['mysql-9.1.0', "ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'", "ALTER USER 'a' 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'"])]
    #[TestWith(['mysql-5.7.44', 'CREATE USER a', "CREATE USER 'a'"])]
    #[TestWith(['mysql-5.7.44', 'ALTER USER a ACCOUNT LOCK', "ALTER USER 'a' ACCOUNT LOCK"])]
    #[TestWith(['mysql-8.4.7', 'GRANT r TO u', "GRANT 'r' TO 'u'"])]
    public function testWriteProducesAFixedPointForEveryAccountStatement(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, AccountCommands::write($statement)?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}

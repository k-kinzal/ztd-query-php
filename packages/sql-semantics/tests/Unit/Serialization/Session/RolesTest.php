<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Configuration\Role\SetExplicitRolesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\Roles;

#[CoversClass(Roles::class)]
#[Medium]
final class RolesTest extends TestCase
{
    public function testWriteDoesNotChangeRoleSelectionIntoSystemVariableAssignment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET ROLE NONE');
        self::assertSame('SET ROLE NONE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($statement::class, $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))::class);
    }

    public function testAccountsQuoteNamesWithSqlPunctuationAsSingleNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET ROLE 'reader'");
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName("x'; DROP TABLE t; --", 'local@host')]);
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertInstanceOf(SetExplicitRolesStatement::class, $again);
        self::assertCount(1, $again->roles);
        self::assertSame("x'; DROP TABLE t; --", $again->roles[0]->username);
        self::assertSame('local@host', $again->roles[0]->host);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'GRANT PROXY ON r@h TO u@h', "GRANT PROXY ON 'r'@'h' TO 'u'@'h'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'DROP USER u@h, v', "DROP USER 'u'@'h', 'v'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.0.44', "GRANT r1@h, 'r2'@'%' TO u@x WITH ADMIN OPTION", "GRANT 'r1'@'h', 'r2'@'%' TO 'u'@'x' WITH ADMIN OPTION"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.1.0', 'REVOKE r1@h FROM u@h', "REVOKE 'r1'@'h' FROM 'u'@'h'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.2.0', 'SET ROLE r1@h', "SET ROLE 'r1'@'h'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.3.0', 'SET DEFAULT ROLE r1@h TO u@h', "SET DEFAULT ROLE 'r1'@'h' TO 'u'@'h'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'DROP ROLE r1@h', "DROP ROLE 'r1'@'h'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.0.1', 'CREATE ROLE r1@h', "CREATE ROLE 'r1'@'h'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0', 'ALTER USER u@h DEFAULT ROLE r@h', "ALTER USER 'u'@'h' DEFAULT ROLE 'r'@'h'"])]
    public function testAccountsWriteEachAccountAsOneToken(string $release, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        self::assertSame("'r'@'h'", Roles::accounts([new AccountName('r', 'h')])->toString());
    }
}

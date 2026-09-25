<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Account\AccountCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql\Account;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AccountCommands::class)]
#[Medium]
final class AccountCommandsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'CREATE USER a', Account\CreateUsersStatement::class])]
    #[TestWith(['mysql-5.7.44', 'ALTER USER a ACCOUNT LOCK', Account\AlterUsersStatement::class])]
    #[TestWith(['mysql-8.0.44', 'CREATE ROLE r', Account\CreateRolesStatement::class])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a DEFAULT ROLE ALL', Account\AlterDefaultRolePolicyStatement::class])]
    #[TestWith(['mysql-9.1.0', 'RENAME USER a TO b', Account\RenameUsersStatement::class])]
    #[TestWith(['mysql-5.6.51', 'GRANT PROXY ON p TO u', Privilege\GrantProxyStatement::class])]
    #[TestWith(['mysql-8.4.7', 'REVOKE r FROM u', Privilege\RevokeRolesStatement::class])]
    public function testBindRoutesEachAccountFormToItsStatement(string $version, string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindLeavesOtherCreateFormsToTheirFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (a INT)');
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertNotSame(Account\CreateUsersStatement::class, $statement::class);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testRenamePairsConsecutiveAccounts(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("RENAME USER 'a'@'h' TO CURRENT_USER, c TO d");
        self::assertInstanceOf(Account\RenameUsersStatement::class, $statement);
        self::assertEquals(new AccountName('a', 'h'), $statement->renames[0]->from);
        self::assertSame(CurrentAccount::Authenticated, $statement->renames[0]->to);
        self::assertEquals(new AccountName('d'), $statement->renames[1]->to);
        self::assertSame("RENAME USER 'a'@'h' TO CURRENT_USER, 'c' TO 'd'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith([Dialect::MySql, 'rename user a to b, c to d', Account\RenameUsersStatement::class, 'RENAME USER \'a\' TO \'b\', \'c\' TO \'d\''])]
    #[TestWith([Dialect::MySql, 'create user u', Account\CreateUsersStatement::class, 'CREATE USER \'u\''])]
    #[TestWith([Dialect::MySql, 'alter user u account lock', Account\AlterUsersStatement::class, 'ALTER USER \'u\' ACCOUNT LOCK'])]
    #[TestWith([Dialect::MySql, 'drop user u', \SqlSemantics\Model\Statement\Definition\MySql\DropUsersStatement::class, 'DROP USER \'u\''])]
    #[TestWith([Dialect::PostgreSql, 'ALTER USER u WITH SUPERUSER', \SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement::class, 'ALTER ROLE "u" SUPERUSER'])]
    public function testBindRoutesLowerCaseAccountCommands(Dialect $dialect, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRenamePairsParsedAccounts(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO 1')->origin;
        $statement = AccountCommands::rename($origin, (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('RENAME USER a TO b'), new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertSame("RENAME USER 'a' TO 'b'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\DropUsersStatement::class)]
#[Medium]
final class DropUsersStatementTest extends TestCase
{
    public function testWithAccountsReplacesTheTargetsWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP USER 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        $changed = $statement->withAccounts([new AccountName('writer', '%')]);
        self::assertInstanceOf(AccountName::class, $statement->accounts[0]);
        self::assertSame('reader', $statement->accounts[0]->username);
        self::assertSame("DROP USER 'writer'@'%'", $changed->toString());
    }

    public function testWithIfExistsKeepsTheAccountIdentities(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP USER 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertSame("DROP USER IF EXISTS 'reader'@'localhost'", $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP USER 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->accounts, $copy->accounts);
        self::assertSame($statement->ifExists, $copy->ifExists);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP USER 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithAccountsKeepsTheCurrentAccountAsASymbol(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP USER 'reader'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        $changed = $statement->withAccounts([CurrentAccount::Authenticated]);
        self::assertSame([CurrentAccount::Authenticated], $changed->accounts);
        self::assertSame('DROP USER CURRENT_USER', $changed->toString());
    }

    public function testWithIfExistsRejectsAPolicyMissingFromMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("DROP USER 'reader'");
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIfExists(true);
    }

}

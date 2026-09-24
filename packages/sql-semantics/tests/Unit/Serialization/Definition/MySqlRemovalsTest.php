<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\MySqlRemovals::class)]
#[Medium]
final class MySqlRemovalsTest extends TestCase
{
    public function testWriteReturnsNullForAnUnrelatedStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\MySqlRemovals::write($statement));
    }

    public function testAccountsQuoteUsernamesAndHostsIndependently(): void
    {
        $accounts = [new AccountName("reader'; DROP TABLE t", 'local@host'), CurrentAccount::Authenticated];
        $sql = 'DROP USER ' . \SqlSemantics\Serialization\Definition\MySqlRemovals::accounts($accounts)->toString();
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\DropUsersStatement::class, $statement);
        self::assertInstanceOf(AccountName::class, $statement->accounts[0]);
        self::assertSame("reader'; DROP TABLE t", $statement->accounts[0]->username);
        self::assertSame('local@host', $statement->accounts[0]->host);
        self::assertSame(CurrentAccount::Authenticated, $statement->accounts[1]);
    }

    #[TestWith(['DROP USER IF EXISTS \'u\', CURRENT_USER'])]
    #[TestWith(['DROP USER \'u\''])]
    #[TestWith(['DROP ROLE IF EXISTS \'r\''])]
    #[TestWith(['DROP ROLE \'r1\', \'r2\''])]
    #[TestWith(['DROP RESOURCE GROUP `g` FORCE'])]
    #[TestWith(['DROP RESOURCE GROUP `g`'])]
    #[TestWith(['DROP DATABASE IF EXISTS `d`'])]
    #[TestWith(['DROP DATABASE `d`'])]
    #[TestWith(['DROP EVENT IF EXISTS `s`.`e`'])]
    #[TestWith(['DROP EVENT `e`'])]
    #[TestWith(['DROP SERVER IF EXISTS `sv`'])]
    #[TestWith(['DROP SERVER `sv`'])]
    public function testWriteSpellsEachNamedRemoval(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertSame($sql, \SqlSemantics\Serialization\Definition\MySqlRemovals::write($statement)?->toString());
    }
}

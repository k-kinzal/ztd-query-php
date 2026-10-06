<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\AccountRules;

#[CoversClass(AccountRules::class)]
#[Medium]
final class AccountRulesTest extends TestCase
{
    public function testStatementLowersEveryStatementRule(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u', (new Semantics(Dialect::MySql))->analyze('grant select on *.* to u')->toString());
        self::assertSame('REVOKE SELECT ON *.* FROM u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('revoke select on *.* from u')->toString());
        self::assertSame('SET ROLE r', (new Semantics(Dialect::MySql))->analyze('set role r')->toString());
        self::assertSame('DROP RESOURCE GROUP g', (new Semantics(Dialect::MySql))->analyze('drop resource group g')->toString());
    }

    public function testDefinitionLowersTheRoutedUserStatements(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED BY 'x'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create user u identified by 'x'")->toString());
    }

    public function testRenameUsersLowersTheRenameList(): void
    {
        self::assertSame('RENAME USER a TO b', (new Semantics(Dialect::MySql))->analyze('rename user a to b')->toString());
    }

    public function testSetPasswordLowersTheStatement(): void
    {
        self::assertSame("SET PASSWORD FOR u = 'x'", (new Semantics(Dialect::MySql))->analyze("set password for u = 'x'")->toString());
    }

    public function testPasswordLowersTheOperandOfASetListItem(): void
    {
        self::assertSame("SET @a = 1, PASSWORD = PASSWORD('x')", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set @a = 1, password = password('x')")->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\AccountRules;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AccountRules::class)]
#[Medium]
final class AccountRulesTest extends TestCase
{
    public function testStatementLowersEveryStatementRule(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u', (new Inspection())->inspect('grant select on *.* to u')->toString());
        self::assertSame('REVOKE SELECT ON *.* FROM u', (new Inspection('mysql-5.6.51'))->inspect('revoke select on *.* from u')->toString());
        self::assertSame('SET ROLE r', (new Inspection())->inspect('set role r')->toString());
        self::assertSame('DROP RESOURCE GROUP g', (new Inspection())->inspect('drop resource group g')->toString());
    }

    public function testDefinitionLowersTheRoutedUserStatements(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED BY 'x'", (new Inspection('mysql-5.7.44'))->inspect("create user u identified by 'x'")->toString());
    }

    public function testRenameUsersLowersTheRenameList(): void
    {
        self::assertSame('RENAME USER a TO b', (new Inspection())->inspect('rename user a to b')->toString());
    }

    public function testSetPasswordLowersTheStatement(): void
    {
        self::assertSame("SET PASSWORD FOR u = 'x'", (new Inspection())->inspect("set password for u = 'x'")->toString());
    }

    public function testPasswordLowersTheOperandOfASetListItem(): void
    {
        self::assertSame("SET @a = 1, PASSWORD = PASSWORD('x')", (new Inspection('mysql-5.6.51'))->inspect("set @a = 1, password = password('x')")->toString());
    }
}

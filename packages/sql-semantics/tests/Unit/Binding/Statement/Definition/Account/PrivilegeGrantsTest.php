<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Account\PrivilegeGrants;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Privilege\RoleExclusion;
use SqlSemantics\Model\Definition\Privilege\RoleSelection;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeGrants::class)]
#[Medium]
final class PrivilegeGrantsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "GRANT SELECT, INSERT (a) ON TABLE t TO u IDENTIFIED BY 'x' REQUIRE SSL WITH GRANT OPTION MAX_USER_CONNECTIONS 1", Privilege\GrantPrivilegesStatement::class, "GRANT SELECT, INSERT(`a`) ON TABLE `t` TO 'u' IDENTIFIED BY 'x' REQUIRE SSL WITH GRANT OPTION MAX_USER_CONNECTIONS 1"])]
    #[TestWith(['mysql-5.7.44', 'GRANT ALL ON FUNCTION app.f TO u', Privilege\GrantAllPrivilegesStatement::class, "GRANT ALL PRIVILEGES ON FUNCTION `app`.`f` TO 'u'"])]
    #[TestWith(['mysql-5.7.44', "GRANT PROXY ON 'p'@'h' TO u WITH GRANT OPTION", Privilege\GrantProxyStatement::class, "GRANT PROXY ON 'p' @'h' TO 'u' WITH GRANT OPTION"])]
    #[TestWith(['mysql-8.0.44', 'GRANT r, s@h TO u, CURRENT_USER WITH ADMIN OPTION', Privilege\GrantRolesStatement::class, "GRANT 'r', 's' @'h' TO 'u', CURRENT_USER WITH ADMIN OPTION"])]
    #[TestWith(['mysql-8.4.7', 'GRANT BACKUP_ADMIN, RELOAD ON *.* TO u WITH GRANT OPTION AS g WITH ROLE ALL EXCEPT r', Privilege\GrantPrivilegesStatement::class, "GRANT `BACKUP_ADMIN`, RELOAD ON *.* TO 'u' WITH GRANT OPTION AS 'g' WITH ROLE ALL EXCEPT 'r'"])]
    #[TestWith(['mysql-9.1.0', 'GRANT ALL PRIVILEGES ON app.* TO u', Privilege\GrantAllPrivilegesStatement::class, "GRANT ALL PRIVILEGES ON `app`.* TO 'u'"])]
    public function testBindWritesEveryGrantFormBackAsAFixedPoint(string $version, string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['mysql-8.4.7', 'GRANT SELECT ON PROCEDURE p TO u'])]
    #[TestWith(['mysql-8.4.7', 'GRANT BACKUP_ADMIN ON app.* TO u'])]
    #[TestWith(['mysql-5.7.44', 'GRANT EXECUTE ON FUNCTION *.* TO u'])]
    #[TestWith(['mysql-5.6.51', 'GRANT SUPER ON app.* TO u'])]
    public function testBindRejectsPrivilegesOutsideTheirLevel(string $version, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeLevel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
    }

    public function testGranteesKeepLegacyCredentials(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse("GRANT SELECT ON *.* TO a, b IDENTIFIED BY 'x'");
        $grantees = PrivilegeGrants::grantees($tree->find('grant_command')[0], new Identifiers(Dialect::MySql));
        self::assertEquals(new AccountName('a'), $grantees[0]);
        self::assertInstanceOf(AccountDefinition::class, $grantees[1]);
    }

    public function testGranteesReadPlainMySql8Recipients(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT SELECT ON *.* TO a, CURRENT_USER');
        self::assertEquals([new AccountName('a'), CurrentAccount::Authenticated], PrivilegeGrants::grantees($tree->find('grant')[0], new Identifiers(Dialect::MySql)));
    }

    public function testGrantorReadsEachRoleSelection(): void
    {
        $parser = new DialectParser(Dialect::MySql, 'mysql-8.4.7');
        self::assertNull(PrivilegeGrants::grantor($parser->parse('GRANT SELECT ON *.* TO a')->find('grant')[0], new Identifiers(Dialect::MySql)));
        self::assertNull(PrivilegeGrants::grantor($parser->parse('GRANT SELECT ON *.* TO a AS g')->find('grant')[0], new Identifiers(Dialect::MySql))?->roles);
        self::assertSame(SessionRolePolicy::Default, PrivilegeGrants::grantor($parser->parse('GRANT SELECT ON *.* TO a AS g WITH ROLE DEFAULT')->find('grant')[0], new Identifiers(Dialect::MySql))?->roles);
        self::assertSame(SessionRolePolicy::All, PrivilegeGrants::grantor($parser->parse('GRANT SELECT ON *.* TO a AS g WITH ROLE ALL')->find('grant')[0], new Identifiers(Dialect::MySql))?->roles);
        self::assertEquals(new RoleSelection([new AccountName('r')]), PrivilegeGrants::grantor($parser->parse('GRANT SELECT ON *.* TO a AS g WITH ROLE r')->find('grant')[0], new Identifiers(Dialect::MySql))?->roles);
        self::assertEquals(new RoleExclusion([new AccountName('r')]), PrivilegeGrants::grantor($parser->parse('GRANT SELECT ON *.* TO a AS g WITH ROLE ALL EXCEPT r')->find('grant')[0], new Identifiers(Dialect::MySql))?->roles);
    }
}

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
use SqlSemantics\Binding\Statement\Definition\Account\PrivilegeLists;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeLists::class)]
#[Medium]
final class PrivilegeListsTest extends TestCase
{
    public function testPrivilegesSeparateStaticColumnAndDynamicPrivileges(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT CREATE TEMPORARY TABLES, UPDATE (a, b), backup_admin ON t TO u');
        self::assertEquals([
            StaticPrivilege::CreateTemporaryTables,
            new ColumnPrivilege(StaticPrivilege::Update, ['a', 'b']),
            new DynamicPrivilege('backup_admin'),
        ], PrivilegeLists::privileges($tree->find('role_or_privilege_list')[0], new Identifiers(Dialect::MySql)));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testPrivilegesReadLegacyPrivilegeLists(string $version): void
    {
        $tree = (new DialectParser(Dialect::MySql, $version))->parse('GRANT SHOW VIEW, REPLICATION CLIENT ON *.* TO u');
        self::assertSame([StaticPrivilege::ShowView, StaticPrivilege::ReplicationClient], PrivilegeLists::privileges($tree->find('grant_privileges')[0], new Identifiers(Dialect::MySql)));
    }

    public function testPrivilegesRejectARoleWithAHost(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleGrant->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT r@h ON *.* TO u');
    }

    public function testRolesKeepNamesAndHosts(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("GRANT r, 's'@'h' TO u");
        self::assertEquals([new AccountName('r'), new AccountName('s', 'h')], PrivilegeLists::roles($tree->find('role_or_privilege_list')[0], new Identifiers(Dialect::MySql)));
    }

    public function testRolesRejectAPrivilegeKeyword(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleGrant->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE r, SELECT FROM u');
    }

    public function testKeywordStopsBeforeTheColumnList(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT REFERENCES (a) ON t TO u');
        $element = $tree->find('role_or_privilege')[0];
        self::assertSame('REFERENCES', PrivilegeLists::keyword($element, $element->find('opt_column_list')[0]));
    }

    public function testKeywordReadsTheSchemasSynonymAsDatabases(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.0.44'))->parse('GRANT SHOW SCHEMAS ON *.* TO u');
        self::assertSame('SHOW DATABASES', PrivilegeLists::keyword($tree->find('role_or_privilege')[0], null));
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('GRANT SHOW SCHEMAS ON *.* TO u');
        self::assertSame("GRANT SHOW DATABASES ON *.* TO 'u'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testColumnsReadTheColumnNames(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT INSERT (`b`, a) ON t TO u');
        $element = $tree->find('role_or_privilege')[0];
        self::assertEquals(new ColumnPrivilege(StaticPrivilege::Insert, ['b', 'a']), PrivilegeLists::columns($element, StaticPrivilege::Insert, $element->find('opt_column_list')[0], new Identifiers(Dialect::MySql)));
    }

    public function testColumnsRejectAPrivilegeWithoutColumnScope(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT INSERT (a) ON t TO u');
        $element = $tree->find('role_or_privilege')[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnPrivilege->message());
        PrivilegeLists::columns($element, StaticPrivilege::Delete, $element->find('opt_column_list')[0], new Identifiers(Dialect::MySql));
    }

    public function testPrivilegesRejectADynamicPrivilegeWithColumns(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnPrivilege->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('GRANT backup_admin (a) ON t TO u');
    }

    public function testDynamicKeepsTheSpelledName(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('GRANT XA_RECOVER_ADMIN ON *.* TO u');
        self::assertSame('XA_RECOVER_ADMIN', PrivilegeLists::dynamic($tree->find('role_or_privilege')[0], 'XA_RECOVER_ADMIN')->name);
    }

    #[TestWith(['mysql-5.6.51', 'GRANT ALL PRIVILEGES ON *.* TO u', 'grant_command', true])]
    #[TestWith(['mysql-5.7.44', 'REVOKE ALL, GRANT OPTION FROM u', 'revoke_command', true])]
    #[TestWith(['mysql-8.4.7', 'GRANT ALL ON *.* TO u', 'grant', true])]
    #[TestWith(['mysql-8.4.7', 'GRANT SELECT ON *.* TO u', 'grant', false])]
    public function testAllRecognizesTheAllPrivilegesSpelling(string $version, string $sql, string $rule, bool $all): void
    {
        $tree = (new DialectParser(Dialect::MySql, $version))->parse($sql);
        self::assertSame($all, PrivilegeLists::all($tree->find($rule)[0]));
    }

    #[TestWith(['grant xa_recover_admin, select on *.* to u', "GRANT `xa_recover_admin`, SELECT ON *.* TO 'u'"])]
    #[TestWith(['grant select(a), insert (a, b) on t to u', "GRANT SELECT (`a`), INSERT(`a`, `b`) ON TABLE `t` TO 'u'"])]
    #[TestWith(['grant show schemas on *.* to u', "GRANT SHOW DATABASES ON *.* TO 'u'"])]
    #[TestWith(['grant all on t to u', "GRANT ALL PRIVILEGES ON TABLE `t` TO 'u'"])]
    #[TestWith(['revoke all privileges on t from u', "REVOKE ALL PRIVILEGES ON TABLE `t` FROM 'u'"])]
    #[TestWith(['grant r1@h, r2 to u', "GRANT 'r1'@'h', 'r2' TO 'u'"])]
    public function testPrivilegesAndRolesReadLowerCaseLists(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT)')))->bind($sql)));
    }

    public function testRolesRejectARoleWithColumns(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleGrant->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('grant r1(a) to u');
    }
}

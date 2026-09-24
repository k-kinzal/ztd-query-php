<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeRolesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\Privileges::class)]
#[Medium]
final class PrivilegesTest extends TestCase
{
    public function testReadReadsAllPrivilegesWithOrWithoutColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $columns = $binder->bind('GRANT ALL (id, a) ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $columns);
        self::assertEquals([new ColumnPrivilege(Privilege::All, ['id', 'a'])], $columns->privileges);
        $spelled = $binder->bind('REVOKE ALL PRIVILEGES ON t FROM a');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $spelled);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $spelled->privileges);
        $short = $binder->bind('REVOKE ALL ON t FROM a');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $short);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $short->privileges);
    }

    public function testReadReadsEachListedPrivilegeInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT DELETE, TRUNCATE, REFERENCES (id), TRIGGER, MAINTAIN, UPDATE (a) ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::Delete), new ObjectPrivilege(Privilege::Truncate), new ColumnPrivilege(Privilege::References, ['id']), new ObjectPrivilege(Privilege::Trigger), new ObjectPrivilege(Privilege::Maintain), new ColumnPrivilege(Privilege::Update, ['a'])], $statement->privileges);
    }

    public function testPrivilegeAcceptsColumnsOnlyOnColumnarPrivileges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT SELECT (id), INSERT ("a"), UPDATE (id, a), REFERENCES (a), DELETE ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ColumnPrivilege(Privilege::Select, ['id']), new ColumnPrivilege(Privilege::Insert, ['a']), new ColumnPrivilege(Privilege::Update, ['id', 'a']), new ColumnPrivilege(Privilege::References, ['a']), new ObjectPrivilege(Privilege::Delete)], $statement->privileges);
    }

    #[TestWith(['DELETE'])]
    #[TestWith(['TRUNCATE'])]
    #[TestWith(['TRIGGER'])]
    #[TestWith(['MAINTAIN'])]
    public function testPrivilegeRejectsColumnsOnNonColumnarPrivileges(string $privilege): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnPrivilege->message());
        $binder->bind('GRANT ' . $privilege . ' (id) ON t TO a');
    }

    public function testNameFoldsKeywordsAndKeepsQuotedCase(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $roles = $binder->bind('GRANT "Select", Staff TO a');
        self::assertInstanceOf(GrantRolesStatement::class, $roles);
        self::assertEquals([new NamedRole('Select'), new NamedRole('staff')], $roles->roles);
        $quoted = $binder->bind('GRANT "select" ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $quoted);
        self::assertEquals([new ObjectPrivilege(Privilege::Select)], $quoted->privileges);
        $spaced = $binder->bind('GRANT "alter system" ON PARAMETER x TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $spaced);
        self::assertEquals([new ObjectPrivilege(Privilege::AlterSystem)], $spaced->privileges);
    }

    #[TestWith(['GRANT SELECT ON t TO a', Privilege::Select])]
    #[TestWith(['GRANT INSERT ON t TO a', Privilege::Insert])]
    #[TestWith(['GRANT UPDATE ON t TO a', Privilege::Update])]
    #[TestWith(['GRANT DELETE ON t TO a', Privilege::Delete])]
    #[TestWith(['GRANT TRUNCATE ON t TO a', Privilege::Truncate])]
    #[TestWith(['GRANT REFERENCES ON t TO a', Privilege::References])]
    #[TestWith(['GRANT TRIGGER ON t TO a', Privilege::Trigger])]
    #[TestWith(['GRANT MAINTAIN ON t TO a', Privilege::Maintain])]
    #[TestWith(['GRANT EXECUTE ON FUNCTION f TO a', Privilege::Execute])]
    #[TestWith(['GRANT USAGE ON SEQUENCE s TO a', Privilege::Usage])]
    #[TestWith(['GRANT CREATE ON DATABASE d TO a', Privilege::Create])]
    #[TestWith(['GRANT CONNECT ON DATABASE d TO a', Privilege::Connect])]
    #[TestWith(['GRANT TEMP ON DATABASE d TO a', Privilege::Temporary])]
    #[TestWith(['GRANT TEMPORARY ON DATABASE d TO a', Privilege::Temporary])]
    #[TestWith(['GRANT SET ON PARAMETER x TO a', Privilege::Set])]
    #[TestWith(['GRANT ALTER SYSTEM ON PARAMETER x TO a', Privilege::AlterSystem])]
    public function testTypeMatchesEveryPrivilegeWord(string $sql, Privilege $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind($sql);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege($expected)], $statement->privileges);
    }

    #[TestWith(['GRANT REFERENCES, IMMUTABLE ON LARGE OBJECT 1 TO a'])]
    #[TestWith(['GRANT "SELECT" ON t TO a'])]
    #[TestWith(['GRANT "Usage" ON SEQUENCE s TO a'])]
    public function testTypeRejectsUnknownOrCaseFoldedWords(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeName->message());
        $binder->bind($sql);
    }

    public function testColumnsReadsEachColumnWithItsCase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT SELECT (Id, "A"), UPDATE (a) ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ColumnPrivilege(Privilege::Select, ['id', 'A']), new ColumnPrivilege(Privilege::Update, ['a'])], $statement->privileges);
    }

    public function testRolesReadsRoleNamesFromThePrivilegeList(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $granted = $binder->bind('GRANT staff, "select", "CURRENT_USER" TO alice');
        self::assertInstanceOf(GrantRolesStatement::class, $granted);
        self::assertEquals([new NamedRole('staff'), new NamedRole('select'), new NamedRole('CURRENT_USER')], $granted->roles);
        $revoked = $binder->bind('REVOKE staff, ops FROM alice, bob');
        self::assertInstanceOf(RevokeRolesStatement::class, $revoked);
        self::assertEquals([new NamedRole('staff'), new NamedRole('ops')], $revoked->roles);
    }

    #[TestWith(['GRANT public TO a', InputViolation::RoleReference])]
    #[TestWith(['GRANT none TO a', InputViolation::RoleReference])]
    #[TestWith(['REVOKE staff, "none" FROM a', InputViolation::RoleReference])]
    #[TestWith(['GRANT SELECT (a) TO b', InputViolation::ColumnPrivilege])]
    #[TestWith(['REVOKE staff (a) FROM b', InputViolation::ColumnPrivilege])]
    public function testRolesRejectsPublicNoneAndColumnLists(string $sql, InputViolation $violation): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        $binder->bind($sql);
    }

    public function testOptionsReadsExplicitAndImpliedValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT staff TO a WITH ADMIN OPTION, SET FALSE, INHERIT TRUE, admin false');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertEquals([new RoleGrantOption(RoleGrantAttribute::Admin, true), new RoleGrantOption(RoleGrantAttribute::Set, false), new RoleGrantOption(RoleGrantAttribute::Inherit, true), new RoleGrantOption(RoleGrantAttribute::Admin, false)], $statement->options);
        $plain = $binder->bind('GRANT staff TO a');
        self::assertInstanceOf(GrantRolesStatement::class, $plain);
        self::assertSame([], $plain->options);
    }

    #[TestWith(['ADMIN', RoleGrantAttribute::Admin])]
    #[TestWith(['INHERIT', RoleGrantAttribute::Inherit])]
    #[TestWith(['SET', RoleGrantAttribute::Set])]
    #[TestWith(['"set"', RoleGrantAttribute::Set])]
    public function testAttributeReadsEachOptionWord(string $word, RoleGrantAttribute $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ' . $word . ' OPTION FOR staff FROM alice');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame($expected, $statement->option);
    }

    #[TestWith(['GRANT a TO b WITH FOO OPTION'])]
    #[TestWith(['GRANT a TO b WITH "ADMIN" OPTION'])]
    #[TestWith(['REVOKE nested OPTION FOR staff FROM alice'])]
    public function testAttributeRejectsUnknownOrCaseFoldedWords(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleGrantOption->message());
        $binder->bind($sql);
    }
}

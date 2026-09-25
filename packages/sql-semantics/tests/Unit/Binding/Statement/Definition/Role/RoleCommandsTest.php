<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleKeyword;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AddGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleSetStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\RenameRoleStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\RoleCommands::class)]
#[Medium]
final class RoleCommandsTest extends TestCase
{
    public function testBindLeavesOtherDialectsToTheirOwnFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP ROLE r');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\DropRolesStatement::class, $statement);
        self::assertSame('r', $statement->roles[0]->username);
    }

    public function testBindRoutesAlterationsAndStoredSettings(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $alteration = $binder->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $alteration);
        self::assertSame(SessionRole::CurrentUser, $alteration->role);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, false), new RoleMembers([new NamedRole('alice')])], $alteration->options);
        self::assertSame(StatementKind::Alter, $alteration->kind);
        $setting = $binder->bind('ALTER ROLE r SET NAMES');
        self::assertInstanceOf(AlterRoleSetStatement::class, $setting);
        self::assertEquals(new NamedRole('r'), $setting->role);
    }

    public function testBindDelegatesPrivilegeOperations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT SELECT ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame(StatementKind::Grant, $statement->kind);
        self::assertEquals([new NamedRole('a')], $statement->grantees);
    }

    #[TestWith(['ROLE', RoleKeyword::Role])]
    #[TestWith(['USER', RoleKeyword::User])]
    #[TestWith(['GROUP', RoleKeyword::Group])]
    public function testCreateKeepsTheKeywordAndReadsTheNameAndOptions(string $keyword, RoleKeyword $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ' . $keyword . ' "x""y" WITH LOGIN CREATEDB');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertSame($expected, $statement->keyword);
        self::assertEquals(new NamedRole('x"y'), $statement->name);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, true), new RoleAttribute(RoleCapability::CreateDb, true)], $statement->options);
        self::assertSame(StatementKind::Create, $statement->kind);
    }

    public function testCreateReadsNoOptionsWithoutAList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE GROUP g');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertSame([], $statement->options);
        self::assertSame(RoleKeyword::Group, $statement->keyword);
    }

    #[TestWith(['CREATE ROLE CURRENT_USER'])]
    #[TestWith(['CREATE USER SESSION_USER'])]
    #[TestWith(['CREATE ROLE public'])]
    public function testCreateRejectsSymbolicNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleName->message());
        $binder->bind($sql);
    }

    public function testGroupSeparatesAddedFromDroppedMembers(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $added = $binder->bind('ALTER GROUP staff ADD USER alice, CURRENT_USER');
        self::assertInstanceOf(AddGroupMembersStatement::class, $added);
        self::assertEquals(new NamedRole('staff'), $added->group);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser], $added->members);
        self::assertSame(StatementKind::Alter, $added->kind);
        $dropped = $binder->bind('ALTER GROUP CURRENT_USER DROP USER a');
        self::assertInstanceOf(DropGroupMembersStatement::class, $dropped);
        self::assertSame(SessionRole::CurrentUser, $dropped->group);
        self::assertEquals([new NamedRole('a')], $dropped->members);
    }

    #[TestWith(['ALTER GROUP public ADD USER a'])]
    #[TestWith(['ALTER GROUP g ADD USER public'])]
    #[TestWith(['ALTER GROUP g DROP USER none'])]
    public function testGroupRejectsPublicAndNoneAsGroupOrMember(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind($sql);
    }

    #[TestWith(['ROLE'])]
    #[TestWith(['USER'])]
    #[TestWith(['GROUP'])]
    public function testRenameCollapsesRoleUserAndGroup(string $keyword): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ' . $keyword . ' r RENAME TO "CURRENT_USER"');
        self::assertInstanceOf(RenameRoleStatement::class, $statement);
        self::assertEquals(new NamedRole('r'), $statement->role);
        self::assertEquals(new NamedRole('CURRENT_USER'), $statement->newName);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testRenameLeavesOtherRenamesToTheirFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t RENAME TO s');
        self::assertNotInstanceOf(RenameRoleStatement::class, $statement);
        self::assertSame(StatementKind::Rename, $statement->kind);
    }

    #[TestWith(['ALTER ROLE r RENAME TO CURRENT_USER'])]
    #[TestWith(['ALTER ROLE CURRENT_ROLE RENAME TO s'])]
    #[TestWith(['ALTER USER r RENAME TO public'])]
    public function testRenameRejectsSymbolicNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleName->message());
        $binder->bind($sql);
    }

    public function testSpecReadsSessionRolesAndQuotedNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $session = $binder->bind('ALTER ROLE SESSION_USER NOLOGIN');
        self::assertInstanceOf(AlterRoleStatement::class, $session);
        self::assertSame(SessionRole::SessionUser, $session->role);
        $quoted = $binder->bind('ALTER USER "CURRENT_USER" LOGIN');
        self::assertInstanceOf(AlterRoleStatement::class, $quoted);
        self::assertEquals(new NamedRole('CURRENT_USER'), $quoted->role);
    }

    public function testSpecRejectsPublic(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind('ALTER ROLE public NOLOGIN');
    }

    #[TestWith(['DROP GROUP IF EXISTS "CURRENT_USER"', true])]
    #[TestWith(['DROP ROLE "CURRENT_USER"', false])]
    #[TestWith(['DROP USER IF EXISTS "CURRENT_USER"', true])]
    public function testListReadsTheDroppedRolesAndIfExists(string $sql, bool $ifExists): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        self::assertSame($ifExists, $statement->ifExists);
        self::assertEquals([new NamedRole('CURRENT_USER')], $statement->roles);
        self::assertSame(StatementKind::Drop, $statement->kind);
    }

    public function testListReadsEveryRoleInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROLE a, b, "c d"');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        self::assertEquals([new NamedRole('a'), new NamedRole('b'), new NamedRole('c d')], $statement->roles);
        self::assertFalse($statement->ifExists);
    }

    #[TestWith(['DROP ROLE CURRENT_ROLE'])]
    #[TestWith(['DROP GROUP a, public'])]
    public function testListRejectsSymbolicNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleName->message());
        $binder->bind($sql);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsLowercaseRoleCommands')]
    public function testBindReadsLowercaseRoleCommands(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement::class . ' => ' . (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsLowercaseRoleCommands(): iterable
    {
        return [
            'create user u (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create user u', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\CreateRoleStatement => CREATE USER "u"'],
            'create role r login (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create role r login', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\CreateRoleStatement => CREATE ROLE "r" LOGIN'],
            'create group g (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create group g', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\CreateRoleStatement => CREATE GROUP "g"'],
            'alter group g add user a, b (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter group g add user a, b', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\AddGroupMembersStatement => ALTER GROUP "g" ADD USER "a", "b"'],
            'alter group g drop user a (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter group g drop user a', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\DropGroupMembersStatement => ALTER GROUP "g" DROP USER "a"'],
            'alter role r rename to s (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter role r rename to s', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\RenameRoleStatement => ALTER ROLE "r" RENAME TO "s"'],
            'alter user r rename to s (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter user r rename to s', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\RenameRoleStatement => ALTER ROLE "r" RENAME TO "s"'],
            'alter group r rename to s (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter group r rename to s', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\RenameRoleStatement => ALTER ROLE "r" RENAME TO "s"'],
            'ALTER SCHEMA a RENAME TO b (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER SCHEMA a RENAME TO b', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Catalog\\RenameObjectStatement => ALTER SCHEMA "a" RENAME TO "b"'],
            'drop role if exists a, b (PostgreSql)' => [Dialect::PostgreSql, null, [], 'drop role if exists a, b', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\DropRolesStatement => DROP ROLE IF EXISTS "a", "b"'],
            'drop user a (PostgreSql)' => [Dialect::PostgreSql, null, [], 'drop user a', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Role\\DropRolesStatement => DROP ROLE "a"'],
        ];
    }
}

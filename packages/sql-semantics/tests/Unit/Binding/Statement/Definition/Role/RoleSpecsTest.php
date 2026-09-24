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
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AddGroupMembersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\RoleSpecs::class)]
#[Medium]
final class RoleSpecsTest extends TestCase
{
    public function testNameKeepsQuotedSessionWordsAsNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $created = $binder->bind('CREATE ROLE "CURRENT_USER"');
        self::assertInstanceOf(CreateRoleStatement::class, $created);
        self::assertEquals(new NamedRole('CURRENT_USER'), $created->name);
        $dropped = $binder->bind('DROP GROUP IF EXISTS "Public"');
        self::assertInstanceOf(DropRolesStatement::class, $dropped);
        self::assertEquals([new NamedRole('Public')], $dropped->roles);
    }

    #[TestWith(['CREATE ROLE CURRENT_USER'])]
    #[TestWith(['CREATE ROLE public'])]
    #[TestWith(['DROP ROLE CURRENT_ROLE'])]
    #[TestWith(['ALTER ROLE r RENAME TO SESSION_USER'])]
    public function testNameRejectsSessionRolesAndPublic(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleName->message());
        $binder->bind($sql);
    }

    #[TestWith(['CURRENT_USER', SessionRole::CurrentUser])]
    #[TestWith(['CURRENT_ROLE', SessionRole::CurrentRole])]
    #[TestWith(['SESSION_USER', SessionRole::SessionUser])]
    public function testReferenceReadsEachSessionRole(string $word, SessionRole $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER ' . $word . ' WITH NOLOGIN');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertSame($expected, $statement->role);
    }

    public function testReferenceReadsPlainAndQuotedNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $folded = $binder->bind('ALTER ROLE Alice NOLOGIN');
        self::assertInstanceOf(AlterRoleStatement::class, $folded);
        self::assertEquals(new NamedRole('alice'), $folded->role);
        $quoted = $binder->bind('ALTER ROLE "Alice" NOLOGIN');
        self::assertInstanceOf(AlterRoleStatement::class, $quoted);
        self::assertEquals(new NamedRole('Alice'), $quoted->role);
    }

    #[TestWith(['ALTER ROLE public NOLOGIN'])]
    #[TestWith(['ALTER ROLE "public" NOLOGIN'])]
    #[TestWith(['ALTER ROLE none NOLOGIN'])]
    #[TestWith(['GRANT TRUNCATE ON t TO a GRANTED BY public'])]
    #[TestWith(['GRANT SELECT ON t TO a GRANTED BY none'])]
    public function testReferenceRejectsPublicAndNone(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind($sql);
    }

    public function testGranteeAcceptsPublicAndDropsTheGroupNoiseWord(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT SELECT ON t TO PUBLIC, GROUP alice, "public", GROUP CURRENT_ROLE, "CURRENT_USER"');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([PublicRole::Public, new NamedRole('alice'), PublicRole::Public, SessionRole::CurrentRole, new NamedRole('CURRENT_USER')], $statement->grantees);
    }

    #[TestWith(['GRANT SELECT ON t TO none'])]
    #[TestWith(['GRANT SELECT ON t TO "none"'])]
    #[TestWith(['REVOKE SELECT ON t FROM a, none'])]
    public function testGranteeRejectsNone(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind($sql);
    }

    public function testReferencesReadsEveryMemberInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff ADD USER alice, CURRENT_USER, "SESSION_USER"');
        self::assertInstanceOf(AddGroupMembersStatement::class, $statement);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser, new NamedRole('SESSION_USER')], $statement->members);
    }

    #[TestWith(['ALTER GROUP g ADD USER a, public'])]
    #[TestWith(['GRANT staff TO alice, PUBLIC'])]
    #[TestWith(['GRANT staff TO alice, none'])]
    public function testReferencesRejectsPublicAndNone(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind($sql);
    }

    public function testNamesReadsEveryDroppedRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROLE a, "B", "CURRENT_ROLE"');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        self::assertEquals([new NamedRole('a'), new NamedRole('B'), new NamedRole('CURRENT_ROLE')], $statement->roles);
    }

    public function testNamesRejectsASessionRoleAnywhereInTheList(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleName->message());
        $binder->bind('DROP ROLE a, CURRENT_USER');
    }

    public function testGranteesReadsEveryGranteeDomain(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $granted = $binder->bind('GRANT SELECT ON t TO alice, CURRENT_USER, PUBLIC');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $granted);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser, PublicRole::Public], $granted->grantees);
        $defaults = $binder->bind('ALTER DEFAULT PRIVILEGES GRANT SELECT ON TABLES TO PUBLIC, "None"');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $defaults);
        self::assertEquals([PublicRole::Public, new NamedRole('None')], $defaults->grantees);
    }
}

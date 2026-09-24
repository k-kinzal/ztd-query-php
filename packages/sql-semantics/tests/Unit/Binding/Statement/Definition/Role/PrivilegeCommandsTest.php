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
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeRolesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\PrivilegeCommands::class)]
#[Medium]
final class PrivilegeCommandsTest extends TestCase
{
    public function testBindReadsMembershipGrantsWithOptionsAndGrantor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, "select" TO alice, CURRENT_USER WITH ADMIN OPTION, SET FALSE, INHERIT TRUE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertEquals([new NamedRole('staff'), new NamedRole('select')], $statement->roles);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser], $statement->grantees);
        self::assertEquals([new RoleGrantOption(RoleGrantAttribute::Admin, true), new RoleGrantOption(RoleGrantAttribute::Set, false), new RoleGrantOption(RoleGrantAttribute::Inherit, true)], $statement->options);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertSame(StatementKind::Grant, $statement->kind);
    }

    public function testBindDefaultsTheMembershipOptionsAndGrantor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff TO alice');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertSame([], $statement->options);
        self::assertNull($statement->grantor);
    }

    public function testObjectsReadsAGrantWithColumnsGrantOptionAndGrantor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, GROUP alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ColumnPrivilege(Privilege::Select, ['id']), new ObjectPrivilege(Privilege::Insert)], $statement->privileges);
        $target = $statement->target;
        self::assertInstanceOf(TableTargets::class, $target);
        self::assertCount(1, $target->tables);
        self::assertSame(['public', 't'], $target->tables[0]->name->parts);
        self::assertEquals([PublicRole::Public, new NamedRole('alice')], $statement->grantees);
        self::assertTrue($statement->grantOption);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertSame(StatementKind::Grant, $statement->kind);
    }

    public function testObjectsReadsARevokeWithOptionOnlyGrantorAndBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::Execute)], $statement->privileges);
        $target = $statement->target;
        self::assertInstanceOf(RoutineTargets::class, $target);
        self::assertSame(RoutineClass::Function, $target->class);
        self::assertCount(1, $target->routines);
        $routine = $target->routines[0];
        self::assertInstanceOf(RoutineBySignature::class, $routine);
        self::assertSame(['f'], $routine->name->parts);
        self::assertCount(1, $routine->parameters);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertTrue($statement->grantOptionOnly);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(StatementKind::Revoke, $statement->kind);
    }

    public function testObjectsDefaultsTheOptionalClauses(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $revoked = $binder->bind('REVOKE ALL ON t FROM PUBLIC RESTRICT');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $revoked);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $revoked->privileges);
        self::assertSame([PublicRole::Public], $revoked->grantees);
        self::assertFalse($revoked->grantOptionOnly);
        self::assertNull($revoked->grantor);
        self::assertSame(DropBehavior::Restrict, $revoked->behavior);
        $granted = $binder->bind('GRANT SELECT ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $granted);
        self::assertFalse($granted->grantOption);
        self::assertNull($granted->grantor);
    }

    #[TestWith(['', null])]
    #[TestWith(['ADMIN OPTION FOR ', RoleGrantAttribute::Admin])]
    #[TestWith(['INHERIT OPTION FOR ', RoleGrantAttribute::Inherit])]
    #[TestWith(['SET OPTION FOR ', RoleGrantAttribute::Set])]
    public function testMembershipsReadsTheRevokedOption(string $prefix, ?RoleGrantAttribute $option): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ' . $prefix . 'staff, ops FROM alice, bob GRANTED BY CURRENT_USER CASCADE');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame($option, $statement->option);
        self::assertEquals([new NamedRole('staff'), new NamedRole('ops')], $statement->roles);
        self::assertEquals([new NamedRole('alice'), new NamedRole('bob')], $statement->grantees);
        self::assertSame(SessionRole::CurrentUser, $statement->grantor);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(StatementKind::Revoke, $statement->kind);
    }

    public function testMembershipsDefaultsTheGrantorAndBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame(RoleGrantAttribute::Admin, $statement->option);
        self::assertNull($statement->grantor);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
    }

    #[TestWith(['REVOKE nested OPTION FOR staff FROM alice'])]
    #[TestWith(['REVOKE "ADMIN" OPTION FOR staff FROM alice'])]
    public function testMembershipsRejectsUnknownOptionWords(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleGrantOption->message());
        $binder->bind($sql);
    }

    public function testDefaultsReadsTheScopeClausesInEitherOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $first = $binder->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $first);
        self::assertEquals([new NamedRole('owner')], $first->roles);
        self::assertSame(['app'], $first->schemas);
        self::assertEquals([new ObjectPrivilege(Privilege::Select)], $first->privileges);
        self::assertSame(DefaultPrivilegeTarget::Tables, $first->target);
        self::assertSame([PublicRole::Public], $first->grantees);
        self::assertFalse($first->grantOption);
        self::assertSame(StatementKind::Alter, $first->kind);
        $second = $binder->bind('ALTER DEFAULT PRIVILEGES IN SCHEMA app FOR USER owner, CURRENT_ROLE GRANT ALL ON SEQUENCES TO a WITH GRANT OPTION');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $second);
        self::assertEquals([new NamedRole('owner'), SessionRole::CurrentRole], $second->roles);
        self::assertSame(['app'], $second->schemas);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $second->privileges);
        self::assertSame(DefaultPrivilegeTarget::Sequences, $second->target);
        self::assertTrue($second->grantOption);
    }

    public function testDefaultsReadsRevocationsWithOptionOnlyAndBehavior(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $statement->privileges);
        self::assertSame(DefaultPrivilegeTarget::Functions, $statement->target);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertTrue($statement->grantOptionOnly);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame([], $statement->roles);
        self::assertSame([], $statement->schemas);
        self::assertSame(StatementKind::Alter, $statement->kind);
        $plain = $binder->bind('ALTER DEFAULT PRIVILEGES FOR ROLE o REVOKE SELECT ON TABLES FROM a RESTRICT');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $plain);
        self::assertFalse($plain->grantOptionOnly);
        self::assertSame(DropBehavior::Restrict, $plain->behavior);
        self::assertEquals([new NamedRole('o')], $plain->roles);
    }

    #[TestWith(['TABLES', DefaultPrivilegeTarget::Tables])]
    #[TestWith(['SEQUENCES', DefaultPrivilegeTarget::Sequences])]
    #[TestWith(['FUNCTIONS', DefaultPrivilegeTarget::Functions])]
    #[TestWith(['ROUTINES', DefaultPrivilegeTarget::Functions])]
    #[TestWith(['TYPES', DefaultPrivilegeTarget::Types])]
    #[TestWith(['SCHEMAS', DefaultPrivilegeTarget::Schemas])]
    public function testDefaultsMapsEveryTargetClass(string $class, DefaultPrivilegeTarget $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES GRANT ALL ON ' . $class . ' TO a');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        self::assertSame($expected, $statement->target);
    }

    #[TestWith(['ALTER DEFAULT PRIVILEGES IN SCHEMA s GRANT USAGE ON SCHEMAS TO a'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR ROLE a FOR ROLE b GRANT SELECT ON TABLES TO c'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES IN SCHEMA a IN SCHEMA b GRANT SELECT ON TABLES TO c'])]
    public function testDefaultsRejectsRepeatedScopesAndSchemaRestrictedSchemas(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefaultPrivilegeScope->message());
        $binder->bind($sql);
    }

    #[TestWith(['GRANT INSERT ON DATABASE db TO a'])]
    #[TestWith(['GRANT USAGE ON LARGE OBJECT 1 TO a'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES GRANT INSERT ON SEQUENCES TO a'])]
    public function testCheckRejectsPrivilegesOutsideTheClassDomain(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeTarget->message());
        $binder->bind($sql);
    }

    #[TestWith(['GRANT SELECT (id) ON ALL FUNCTIONS IN SCHEMA s TO a'])]
    #[TestWith(['GRANT ALL (id) ON SEQUENCE s TO a'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES REVOKE ALL (id) ON FUNCTIONS FROM a'])]
    public function testCheckRejectsColumnListsOutsideTables(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnPrivilege->message());
        $binder->bind($sql);
    }

    public function testGrantorReadsNamedAndSessionRolesOrNothing(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $named = $binder->bind('GRANT SELECT ON t TO a GRANTED BY "Bob"');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $named);
        self::assertEquals(new NamedRole('Bob'), $named->grantor);
        $session = $binder->bind('REVOKE staff FROM alice GRANTED BY CURRENT_USER');
        self::assertInstanceOf(RevokeRolesStatement::class, $session);
        self::assertSame(SessionRole::CurrentUser, $session->grantor);
        $absent = $binder->bind('GRANT staff TO alice, CURRENT_USER');
        self::assertInstanceOf(GrantRolesStatement::class, $absent);
        self::assertNull($absent->grantor);
    }

    #[TestWith(['GRANT TRUNCATE ON t TO a GRANTED BY public'])]
    #[TestWith(['GRANT staff TO a GRANTED BY none'])]
    public function testGrantorRejectsPublicAndNone(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind($sql);
    }

    #[TestWith(['', DropBehavior::Default])]
    #[TestWith(['CASCADE', DropBehavior::Cascade])]
    #[TestWith(['RESTRICT', DropBehavior::Restrict])]
    public function testBehaviorReadsTheDependentGrantPolicy(string $policy, DropBehavior $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $objects = $binder->bind('REVOKE SELECT ON t FROM a ' . $policy);
        self::assertInstanceOf(RevokePrivilegesStatement::class, $objects);
        self::assertSame($expected, $objects->behavior);
        $memberships = $binder->bind('REVOKE staff FROM a ' . $policy);
        self::assertInstanceOf(RevokeRolesStatement::class, $memberships);
        self::assertSame($expected, $memberships->behavior);
        $defaults = $binder->bind('ALTER DEFAULT PRIVILEGES REVOKE SELECT ON TABLES FROM a ' . $policy);
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $defaults);
        self::assertSame($expected, $defaults->behavior);
    }

    public function testChildLocatesTheRequiredClausesOfEachForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $objects = $binder->bind('GRANT SELECT ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $objects);
        self::assertCount(1, $objects->privileges);
        self::assertCount(1, $objects->grantees);
        $memberships = $binder->bind('GRANT staff TO a');
        self::assertInstanceOf(GrantRolesStatement::class, $memberships);
        self::assertCount(1, $memberships->roles);
        self::assertCount(1, $memberships->grantees);
        $defaults = $binder->bind('ALTER DEFAULT PRIVILEGES IN SCHEMA s GRANT SELECT ON TABLES TO a');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $defaults);
        self::assertSame(['s'], $defaults->schemas);
        self::assertCount(1, $defaults->grantees);
    }

    #[TestWith(['alter default privileges in schema s grant select on tables to r', 'ALTER DEFAULT PRIVILEGES IN SCHEMA "s" GRANT SELECT ON TABLES TO "r"'])]
    #[TestWith(['alter default privileges for role a revoke grant option for execute on routines from r cascade', 'ALTER DEFAULT PRIVILEGES FOR ROLE "a" REVOKE GRANT OPTION FOR EXECUTE ON FUNCTIONS FROM "r" CASCADE'])]
    #[TestWith(['alter default privileges revoke usage on types from r restrict', 'ALTER DEFAULT PRIVILEGES REVOKE USAGE ON TYPES FROM "r" RESTRICT'])]
    public function testDefaultsReadLowercaseKeywords(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }
}

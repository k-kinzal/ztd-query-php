<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokeDefaultPrivilegesStatement::class)]
#[Medium]
final class RevokeDefaultPrivilegesStatementTest extends TestCase
{
    public function testReadsTheDefaultRevocationFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $statement->privileges);
        self::assertSame(DefaultPrivilegeTarget::Functions, $statement->target);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertTrue($statement->grantOptionOnly);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame([], $statement->roles);
        self::assertSame([], $statement->schemas);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testToStringSpellsOutAllPrivilegesAndTheFunctionsClass(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE', 'ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice" CASCADE'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR USER owner REVOKE USAGE ON SEQUENCES FROM alice', 'ALTER DEFAULT PRIVILEGES FOR ROLE "owner" REVOKE USAGE ON SEQUENCES FROM "alice"'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES REVOKE CREATE ON SCHEMAS FROM a RESTRICT', 'ALTER DEFAULT PRIVILEGES REVOKE CREATE ON SCHEMAS FROM "a" RESTRICT'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES IN SCHEMA s REVOKE SELECT ON TABLES FROM PUBLIC', 'ALTER DEFAULT PRIVILEGES IN SCHEMA "s" REVOKE SELECT ON TABLES FROM PUBLIC'])]
    public function testRebindingTheOutputReachesAFixedPoint(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $again);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithPrivilegesReplacesTheCompleteRequestWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $privileges = [new ObjectPrivilege(Privilege::Execute)];
        $changed = $statement->withPrivileges($privileges);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new ObjectPrivilege(Privilege::All)], $statement->privileges);
        self::assertEquals($privileges, $changed->privileges);
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR EXECUTE ON FUNCTIONS FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithPrivilegesRejectsAPrivilegeOutsideTheClassDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPrivileges([new ObjectPrivilege(Privilege::Select)]);
    }

    public function testWithTargetReplacesTheObjectClass(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $changed = $statement->withTarget(DefaultPrivilegeTarget::Tables);
        self::assertNotSame($statement, $changed);
        self::assertSame(DefaultPrivilegeTarget::Functions, $statement->target);
        self::assertSame(DefaultPrivilegeTarget::Tables, $changed->target);
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON TABLES FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGranteesReplacesTheRolesLosingTheDefaults(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $grantees = [PublicRole::Public, SessionRole::CurrentRole];
        $changed = $statement->withGrantees($grantees);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertEquals($grantees, $changed->grantees);
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM PUBLIC, CURRENT_ROLE CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGrantOptionOnlyRevokesTheDefaultsThemselves(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $changed = $statement->withGrantOptionOnly(false);
        self::assertNotSame($statement, $changed);
        self::assertTrue($statement->grantOptionOnly);
        self::assertFalse($changed->grantOptionOnly);
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE ALL PRIVILEGES ON FUNCTIONS FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithBehaviorReplacesTheDependentGrantPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $restricted = $statement->withBehavior(DropBehavior::Restrict);
        self::assertNotSame($statement, $restricted);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(DropBehavior::Restrict, $restricted->behavior);
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($restricted));
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withBehavior(DropBehavior::Default)));
    }

    public function testWithRolesAddsTheDefiningRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $roles = [new NamedRole('owner'), SessionRole::CurrentUser];
        $changed = $statement->withRoles($roles);
        self::assertNotSame($statement, $changed);
        self::assertSame([], $statement->roles);
        self::assertEquals($roles, $changed->roles);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner", CURRENT_USER REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithSchemasAddsTheSchemaSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $changed = $statement->withSchemas(['app', 'x"y']);
        self::assertNotSame($statement, $changed);
        self::assertSame([], $statement->schemas);
        self::assertSame(['app', 'x"y'], $changed->schemas);
        self::assertSame('ALTER DEFAULT PRIVILEGES IN SCHEMA "app", "x""y" REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithSchemasRejectsASelectionForSchemaDefaults(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE CREATE ON SCHEMAS FROM a RESTRICT');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSchemas(['app']);
    }

    public function testWithSchemasRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSchemas(['']);
    }

    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR USER owner IN SCHEMA s REVOKE USAGE ON SEQUENCES FROM alice');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->privileges, $copy->privileges);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->grantees, $copy->grantees);
        self::assertSame($statement->grantOptionOnly, $copy->grantOptionOnly);
        self::assertSame($statement->behavior, $copy->behavior);
        self::assertSame($statement->roles, $copy->roles);
        self::assertSame($statement->schemas, $copy->schemas);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
        self::assertInstanceOf(RevokeDefaultPrivilegesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAPrivilegeOutsideTheClassDomainOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new RevokeDefaultPrivilegesStatement($origin, [new ObjectPrivilege(Privilege::Insert)], DefaultPrivilegeTarget::Types, [PublicRole::Public]);
    }
}

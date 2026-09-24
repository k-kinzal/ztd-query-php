<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleInvariant::class)]
#[Medium]
final class RoleInvariantTest extends TestCase
{
    public function testDialectAcceptsAPostgreSqlOrigin(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        RoleInvariant::dialect($origin);
        self::assertSame(Dialect::PostgreSql, $origin->dialect);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDialectRejectsAnotherDatabaseLanguage(Dialect $dialect): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RoleInvariant::dialect($origin);
    }

    public function testRolesAcceptsNamedAndSessionRoles(): void
    {
        $roles = [new NamedRole('a'), SessionRole::SessionUser];
        RoleInvariant::roles($roles);
        self::assertCount(2, $roles);
    }

    public function testRolesRejectsAnEmptySelection(): void
    {
        $this->expectException(InvalidStructure::class);
        RoleInvariant::roles([]);
    }

    public function testOptionsAcceptsEveryOptionOfADefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $origin = $binder->bind('SELECT 1')->origin;
        $secret = Expression::literal('x', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $secret);
        $options = [
            new RoleAttribute(RoleCapability::Login, true),
            new RolePassword($secret),
            new ConnectionLimit(3),
            new RoleValidity($secret),
            new RoleMembers([new NamedRole('a')]),
            new RoleMemberships([new NamedRole('b')]),
            new RoleAdmins([new NamedRole('c')]),
            new RoleSystemId(1),
        ];
        RoleInvariant::options($origin, $options, true);
        self::assertFalse(RoleInvariant::conflicting($options));
    }

    public function testOptionsAcceptsAlterationOptionsWithoutCreating(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $options = [new RoleAttribute(RoleCapability::Login, false), new ClearedPassword(), new ConnectionLimit(-1), new RoleMembers([SessionRole::CurrentRole])];
        RoleInvariant::options($origin, $options, false);
        self::assertFalse(RoleInvariant::conflicting($options));
    }

    #[DataProvider('providerDefinitionOnlyOptions')]
    public function testOptionsRejectsDefinitionOnlyOptionsInAnAlteration(RoleMemberships|RoleAdmins|RoleSystemId $option): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RoleInvariant::options($origin, [$option], false);
    }

    /**
     * @return iterable<string, array{RoleMemberships|RoleAdmins|RoleSystemId}>
     */
    public static function providerDefinitionOnlyOptions(): iterable
    {
        yield 'memberships' => [new RoleMemberships([new NamedRole('a')])];
        yield 'admins' => [new RoleAdmins([new NamedRole('a')])];
        yield 'sysid' => [new RoleSystemId(1)];
    }

    public function testOptionsRejectsBothFormsOfOneAttribute(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RoleInvariant::options($origin, [new RoleAttribute(RoleCapability::Login, true), new RoleAttribute(RoleCapability::Login, false)], true);
    }

    public function testOptionsRejectsAForeignOrigin(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RoleInvariant::options($origin, [], true);
    }

    public function testConflictingReportsARepeatedProperty(): void
    {
        $secret = Expression::literal('x', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $secret);
        self::assertTrue(RoleInvariant::conflicting([new RolePassword($secret), new ClearedPassword()]));
        self::assertTrue(RoleInvariant::conflicting([new ConnectionLimit(1), new RoleAttribute(RoleCapability::Login, true), new ConnectionLimit(2)]));
        self::assertTrue(RoleInvariant::conflicting([new RoleAttribute(RoleCapability::Inherit, true), new RoleAttribute(RoleCapability::Inherit, true)]));
    }

    public function testConflictingAcceptsDistinctProperties(): void
    {
        self::assertFalse(RoleInvariant::conflicting([]));
        self::assertFalse(RoleInvariant::conflicting([new RoleAttribute(RoleCapability::Login, true), new RoleAttribute(RoleCapability::Inherit, false), new ConnectionLimit(1)]));
    }

    #[DataProvider('providerProperties')]
    public function testPropertyNamesTheAddressedRoleProperty(RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId $option, string $property): void
    {
        self::assertSame($property, RoleInvariant::property($option));
    }

    /**
     * @return iterable<string, array{RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId, string}>
     */
    public static function providerProperties(): iterable
    {
        $secret = Expression::literal('x', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $secret);
        yield 'login' => [new RoleAttribute(RoleCapability::Login, true), 'attribute:LOGIN'];
        yield 'nobypassrls' => [new RoleAttribute(RoleCapability::BypassRls, false), 'attribute:BYPASSRLS'];
        yield 'password' => [new RolePassword($secret), 'password'];
        yield 'password null' => [new ClearedPassword(), 'password'];
        yield 'connection limit' => [new ConnectionLimit(1), 'connection-limit'];
        yield 'valid until' => [new RoleValidity($secret), 'valid-until'];
        yield 'members' => [new RoleMembers([new NamedRole('a')]), 'members'];
        yield 'memberships' => [new RoleMemberships([new NamedRole('a')]), 'memberships'];
        yield 'admins' => [new RoleAdmins([new NamedRole('a')]), 'admins'];
        yield 'sysid' => [new RoleSystemId(1), 'sysid'];
    }

    #[TestWith([null])]
    #[TestWith(['app'])]
    public function testDatabaseAcceptsAnAbsentOrNamedQualifier(?string $database): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        RoleInvariant::database($origin, $database);
        self::assertNotSame('', $database);
    }

    public function testDatabaseRejectsAnEmptyName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RoleInvariant::database($origin, '');
    }

    public function testDatabaseRejectsAForeignOrigin(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RoleInvariant::database($origin, null);
    }
}

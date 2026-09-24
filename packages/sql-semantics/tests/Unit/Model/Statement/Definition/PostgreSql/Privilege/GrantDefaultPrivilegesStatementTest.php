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
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantDefaultPrivilegesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantDefaultPrivilegesStatement::class)]
#[Medium]
final class GrantDefaultPrivilegesStatementTest extends TestCase
{
    public function testReadsTheDefaultGrantFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::Select)], $statement->privileges);
        self::assertSame(DefaultPrivilegeTarget::Tables, $statement->target);
        self::assertSame([PublicRole::Public], $statement->grantees);
        self::assertFalse($statement->grantOption);
        self::assertEquals([new NamedRole('owner')], $statement->roles);
        self::assertSame(['app'], $statement->schemas);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testToStringQuotesTheRoleAndTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT SELECT ON TABLES TO PUBLIC', $statement->toString());
    }

    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC', 'ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT SELECT ON TABLES TO PUBLIC'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR ROLE a, CURRENT_USER IN SCHEMA s, s2 GRANT ALL ON FUNCTIONS TO a WITH GRANT OPTION', 'ALTER DEFAULT PRIVILEGES FOR ROLE "a", CURRENT_USER IN SCHEMA "s", "s2" GRANT ALL PRIVILEGES ON FUNCTIONS TO "a" WITH GRANT OPTION'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES GRANT USAGE ON SCHEMAS TO PUBLIC WITH GRANT OPTION', 'ALTER DEFAULT PRIVILEGES GRANT USAGE ON SCHEMAS TO PUBLIC WITH GRANT OPTION'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES GRANT USAGE ON TYPES TO a', 'ALTER DEFAULT PRIVILEGES GRANT USAGE ON TYPES TO "a"'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR USER owner GRANT EXECUTE ON ROUTINES TO a', 'ALTER DEFAULT PRIVILEGES FOR ROLE "owner" GRANT EXECUTE ON FUNCTIONS TO "a"'])]
    public function testRebindingTheOutputReachesAFixedPoint(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        $again = $binder->bind($statement->toString());
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $again);
        self::assertSame($expected, $again->toString());
    }

    public function testWithPrivilegesReplacesTheCompleteRequestWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $privileges = [new ObjectPrivilege(Privilege::Insert), new ObjectPrivilege(Privilege::Delete)];
        $changed = $statement->withPrivileges($privileges);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new ObjectPrivilege(Privilege::Select)], $statement->privileges);
        self::assertEquals($privileges, $changed->privileges);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT INSERT, DELETE ON TABLES TO PUBLIC', $changed->toString());
    }

    public function testWithPrivilegesRejectsAColumnList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPrivileges([new ColumnPrivilege(Privilege::Select, ['id'])]);
    }

    public function testWithTargetReplacesTheObjectClass(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $changed = $statement->withTarget(DefaultPrivilegeTarget::Sequences);
        self::assertNotSame($statement, $changed);
        self::assertSame(DefaultPrivilegeTarget::Tables, $statement->target);
        self::assertSame(DefaultPrivilegeTarget::Sequences, $changed->target);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT SELECT ON SEQUENCES TO PUBLIC', $changed->toString());
    }

    public function testWithTargetRejectsAClassOutsideThePrivilegeDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTarget(DefaultPrivilegeTarget::Types);
    }

    public function testWithGranteesReplacesTheRecipients(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $grantees = [new NamedRole('alice'), SessionRole::CurrentUser];
        $changed = $statement->withGrantees($grantees);
        self::assertNotSame($statement, $changed);
        self::assertSame([PublicRole::Public], $statement->grantees);
        self::assertEquals($grantees, $changed->grantees);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT SELECT ON TABLES TO "alice", CURRENT_USER', $changed->toString());
    }

    public function testWithGrantOptionAddsTheOnwardGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $changed = $statement->withGrantOption(true);
        self::assertNotSame($statement, $changed);
        self::assertFalse($statement->grantOption);
        self::assertTrue($changed->grantOption);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT SELECT ON TABLES TO PUBLIC WITH GRANT OPTION', $changed->toString());
    }

    public function testWithRolesReplacesOrClearsTheDefiningRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $cleared = $statement->withRoles([]);
        self::assertNotSame($statement, $cleared);
        self::assertEquals([new NamedRole('owner')], $statement->roles);
        self::assertSame([], $cleared->roles);
        self::assertSame('ALTER DEFAULT PRIVILEGES IN SCHEMA "app" GRANT SELECT ON TABLES TO PUBLIC', $cleared->toString());
        $session = $statement->withRoles([SessionRole::CurrentUser, new NamedRole('x"y')]);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE CURRENT_USER, "x""y" IN SCHEMA "app" GRANT SELECT ON TABLES TO PUBLIC', $session->toString());
    }

    public function testWithSchemasReplacesOrClearsTheSchemaSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $cleared = $statement->withSchemas([]);
        self::assertNotSame($statement, $cleared);
        self::assertSame(['app'], $statement->schemas);
        self::assertSame([], $cleared->schemas);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" GRANT SELECT ON TABLES TO PUBLIC', $cleared->toString());
        $two = $statement->withSchemas(['s', 'x"y']);
        self::assertSame(['s', 'x"y'], $two->schemas);
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "s", "x""y" GRANT SELECT ON TABLES TO PUBLIC', $two->toString());
    }

    public function testWithSchemasRejectsASelectionForSchemaDefaults(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES GRANT USAGE ON SCHEMAS TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSchemas(['app']);
    }

    public function testWithSchemasRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSchemas(['']);
    }

    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->privileges, $copy->privileges);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->grantees, $copy->grantees);
        self::assertSame($statement->grantOption, $copy->grantOption);
        self::assertSame($statement->roles, $copy->roles);
        self::assertSame($statement->schemas, $copy->schemas);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
        self::assertInstanceOf(GrantDefaultPrivilegesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsASchemaSelectionForSchemaDefaultsOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new GrantDefaultPrivilegesStatement($origin, [new ObjectPrivilege(Privilege::Usage)], DefaultPrivilegeTarget::Schemas, [PublicRole::Public], false, [], ['app']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Role\PrivilegeCommands;

#[CoversClass(PrivilegeCommands::class)]
#[Medium]
final class PrivilegeCommandsTest extends TestCase
{
    public function testWriteReturnsNullForForeignStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertNull(PrivilegeCommands::write($binder->bind('SELECT 1')));
        self::assertNull(PrivilegeCommands::write($binder->bind('CREATE ROLE r')));
    }

    #[TestWith(['GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, GROUP alice WITH GRANT OPTION GRANTED BY bob', 'GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO PUBLIC, "alice" WITH GRANT OPTION GRANTED BY "bob"'])]
    #[TestWith(['GRANT ALL (id, a) ON t TO a', 'GRANT ALL PRIVILEGES("id", "a") ON TABLE "public"."t" TO "a"'])]
    #[TestWith(['GRANT USAGE, SELECT ON SEQUENCE s, app.s2 TO a', 'GRANT USAGE, SELECT ON SEQUENCE "s", "app"."s2" TO "a"'])]
    #[TestWith(['GRANT CREATE, CONNECT, TEMP ON DATABASE db1, db2 TO a', 'GRANT CREATE, CONNECT, TEMPORARY ON DATABASE "db1", "db2" TO "a"'])]
    #[TestWith(['GRANT SELECT, UPDATE ON LARGE OBJECT 12, 0000013 TO a', 'GRANT SELECT, UPDATE ON LARGE OBJECT 12, 13 TO "a"'])]
    #[TestWith(['GRANT SET, ALTER SYSTEM ON PARAMETER work_mem, app.setting TO a', 'GRANT SET, ALTER SYSTEM ON PARAMETER "work_mem", "app"."setting" TO "a"'])]
    #[TestWith(['GRANT EXECUTE ON FUNCTION f, g(), app.h(IN id integer, OUT message text) TO a', 'GRANT EXECUTE ON FUNCTION "f", "g"(), "app"."h"(IN "id" integer, OUT "message" text) TO "a"'])]
    #[TestWith(['GRANT ALL ON ALL TABLES IN SCHEMA s, s2 TO a', 'GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA "s", "s2" TO "a"'])]
    #[TestWith(['GRANT SELECT ON t TO a GRANTED BY CURRENT_USER', 'GRANT SELECT ON TABLE "public"."t" TO "a" GRANTED BY CURRENT_USER'])]
    #[TestWith(['REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE', 'REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob" CASCADE'])]
    #[TestWith(['REVOKE ALL ON t FROM PUBLIC RESTRICT', 'REVOKE ALL PRIVILEGES ON TABLE "public"."t" FROM PUBLIC RESTRICT'])]
    #[TestWith(['REVOKE SELECT ON t FROM a', 'REVOKE SELECT ON TABLE "public"."t" FROM "a"'])]
    #[TestWith(['GRANT staff, "select" TO alice WITH ADMIN OPTION, SET FALSE, INHERIT TRUE', 'GRANT "staff", "select" TO "alice" WITH ADMIN TRUE, SET FALSE, INHERIT TRUE'])]
    #[TestWith(['GRANT staff TO alice, CURRENT_USER GRANTED BY bob', 'GRANT "staff" TO "alice", CURRENT_USER GRANTED BY "bob"'])]
    #[TestWith(['REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT', 'REVOKE ADMIN OPTION FOR "staff" FROM "alice" RESTRICT'])]
    #[TestWith(['REVOKE INHERIT OPTION FOR staff FROM alice GRANTED BY bob CASCADE', 'REVOKE INHERIT OPTION FOR "staff" FROM "alice" GRANTED BY "bob" CASCADE'])]
    #[TestWith(['REVOKE SET OPTION FOR staff, ops FROM alice, bob', 'REVOKE SET OPTION FOR "staff", "ops" FROM "alice", "bob"'])]
    #[TestWith(['REVOKE staff FROM alice', 'REVOKE "staff" FROM "alice"'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR ROLE owner IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC', 'ALTER DEFAULT PRIVILEGES FOR ROLE "owner" IN SCHEMA "app" GRANT SELECT ON TABLES TO PUBLIC'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES IN SCHEMA app FOR USER owner, CURRENT_ROLE GRANT ALL ON SEQUENCES TO a WITH GRANT OPTION', 'ALTER DEFAULT PRIVILEGES FOR ROLE "owner", CURRENT_ROLE IN SCHEMA "app" GRANT ALL PRIVILEGES ON SEQUENCES TO "a" WITH GRANT OPTION'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE', 'ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTIONS FROM "alice" CASCADE'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES GRANT USAGE, CREATE ON SCHEMAS TO a', 'ALTER DEFAULT PRIVILEGES GRANT USAGE, CREATE ON SCHEMAS TO "a"'])]
    #[TestWith(['ALTER DEFAULT PRIVILEGES FOR ROLE o REVOKE SELECT ON TABLES FROM a RESTRICT', 'ALTER DEFAULT PRIVILEGES FOR ROLE "o" REVOKE SELECT ON TABLES FROM "a" RESTRICT'])]
    public function testWriteWritesEachOperationFormAndStaysFixedAcrossBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $statement = $binder->bind($sql);
        $tree = PrivilegeCommands::write($statement);
        self::assertNotNull($tree);
        self::assertSame($expected, $tree->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($statement->kind, $rebound->kind);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testPrivilegesWritesColumnListsAfterThePrivilege(): void
    {
        $tree = PrivilegeCommands::privileges([new ObjectPrivilege(Privilege::All), new ColumnPrivilege(Privilege::Select, ['id', 'a']), new ColumnPrivilege(Privilege::References, ['x"y'])]);
        self::assertSame('ALL PRIVILEGES, SELECT ("id", "a"), REFERENCES("x""y")', $tree->toString());
        self::assertSame('ALTER SYSTEM', PrivilegeCommands::privileges([new ObjectPrivilege(Privilege::AlterSystem)])->toString());
    }

    public function testOptionWritesExplicitBooleans(): void
    {
        self::assertSame('ADMIN TRUE', PrivilegeCommands::option(new RoleGrantOption(RoleGrantAttribute::Admin, true))->toString());
        self::assertSame('SET FALSE', PrivilegeCommands::option(new RoleGrantOption(RoleGrantAttribute::Set, false))->toString());
        self::assertSame('INHERIT TRUE', PrivilegeCommands::option(new RoleGrantOption(RoleGrantAttribute::Inherit, true))->toString());
    }

    public function testScopeWritesRolesAndSchemasAfterTheHeading(): void
    {
        $full = PrivilegeCommands::scope([new NamedRole('owner'), SessionRole::CurrentRole], ['app', 'x']);
        self::assertCount(5, $full);
        self::assertSame('ALTER DEFAULT PRIVILEGES', $full[0]->toString());
        self::assertSame('FOR ROLE', $full[1]->toString());
        self::assertSame('"owner", CURRENT_ROLE', $full[2]->toString());
        self::assertSame('IN SCHEMA', $full[3]->toString());
        self::assertSame('"app", "x"', $full[4]->toString());
        $bare = PrivilegeCommands::scope([], []);
        self::assertCount(1, $bare);
        self::assertSame('ALTER DEFAULT PRIVILEGES', $bare[0]->toString());
        $schemas = PrivilegeCommands::scope([], ['s']);
        self::assertCount(3, $schemas);
        self::assertSame('IN SCHEMA', $schemas[1]->toString());
    }

    public function testGrantOptionWritesTheClauseOnlyWhenGranted(): void
    {
        self::assertSame([], PrivilegeCommands::grantOption(false));
        $granted = PrivilegeCommands::grantOption(true);
        self::assertCount(1, $granted);
        self::assertSame('WITH GRANT OPTION', $granted[0]->toString());
    }

    public function testOptionOnlyNamesTheRevokedOption(): void
    {
        self::assertSame([], PrivilegeCommands::optionOnly(null));
        $admin = PrivilegeCommands::optionOnly('ADMIN');
        self::assertCount(1, $admin);
        self::assertSame('ADMIN OPTION FOR', $admin[0]->toString());
        self::assertSame('GRANT OPTION FOR', PrivilegeCommands::optionOnly('GRANT')[0]->toString());
    }

    public function testGrantorWritesGrantedByForNamedAndSessionRoles(): void
    {
        self::assertSame([], PrivilegeCommands::grantor(null));
        $session = PrivilegeCommands::grantor(SessionRole::CurrentUser);
        self::assertCount(2, $session);
        self::assertSame('GRANTED BY', $session[0]->toString());
        self::assertSame('CURRENT_USER', $session[1]->toString());
        $named = PrivilegeCommands::grantor(new NamedRole('b"o'));
        self::assertCount(2, $named);
        self::assertSame('"b""o"', $named[1]->toString());
    }

    public function testBehaviorOmitsTheDefaultPolicy(): void
    {
        self::assertSame([], PrivilegeCommands::behavior(DropBehavior::Default));
        $cascade = PrivilegeCommands::behavior(DropBehavior::Cascade);
        self::assertCount(1, $cascade);
        self::assertSame('CASCADE', $cascade[0]->toString());
        self::assertSame('RESTRICT', PrivilegeCommands::behavior(DropBehavior::Restrict)[0]->toString());
    }
}

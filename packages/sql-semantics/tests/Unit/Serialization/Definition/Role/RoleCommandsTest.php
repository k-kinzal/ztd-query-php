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
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Role\RoleCommands;

#[CoversClass(RoleCommands::class)]
#[Medium]
final class RoleCommandsTest extends TestCase
{
    public function testWriteReturnsNullForForeignStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(RoleCommands::write($statement));
    }

    #[TestWith(["CREATE ROLE r WITH LOGIN SYSID 2 PASSWORD 'x' IN GROUP a, CURRENT_USER CONNECTION LIMIT -1 ROLE b ADMIN d VALID UNTIL 'infinity' NOINHERIT NOSUPERUSER CREATEDB NOCREATEROLE REPLICATION NOBYPASSRLS", 'CREATE ROLE "r" LOGIN SYSID 2 PASSWORD \'x\' IN ROLE "a", CURRENT_USER CONNECTION LIMIT -1 ROLE "b" ADMIN "d" VALID UNTIL \'infinity\' NOINHERIT NOSUPERUSER CREATEDB NOCREATEROLE REPLICATION NOBYPASSRLS'])]
    #[TestWith(['CREATE USER u WITH ENCRYPTED PASSWORD $$secret$$', 'CREATE USER "u" PASSWORD $$secret$$'])]
    #[TestWith(['CREATE GROUP g', 'CREATE GROUP "g"'])]
    #[TestWith(['CREATE ROLE r PASSWORD NULL', 'CREATE ROLE "r" PASSWORD NULL'])]
    #[TestWith(['ALTER USER CURRENT_USER WITH NOLOGIN USER alice', 'ALTER ROLE CURRENT_USER NOLOGIN USER "alice"'])]
    #[TestWith(['ALTER ROLE r', 'ALTER ROLE "r"'])]
    #[TestWith(['ALTER GROUP staff ADD USER alice, CURRENT_USER', 'ALTER GROUP "staff" ADD USER "alice", CURRENT_USER'])]
    #[TestWith(['ALTER GROUP g DROP USER a', 'ALTER GROUP "g" DROP USER "a"'])]
    #[TestWith(['ALTER ROLE r RENAME TO s', 'ALTER ROLE "r" RENAME TO "s"'])]
    #[TestWith(['ALTER GROUP r RENAME TO s', 'ALTER ROLE "r" RENAME TO "s"'])]
    #[TestWith(['ALTER USER r RENAME TO "CURRENT_USER"', 'ALTER ROLE "r" RENAME TO "CURRENT_USER"'])]
    #[TestWith(['DROP GROUP IF EXISTS "CURRENT_USER"', 'DROP ROLE IF EXISTS "CURRENT_USER"'])]
    #[TestWith(['DROP USER a, b', 'DROP ROLE "a", "b"'])]
    #[TestWith(['ALTER ROLE ALL IN DATABASE d RESET ALL', 'ALTER ROLE ALL IN DATABASE "d" RESET ALL'])]
    #[TestWith(['ALTER ROLE SESSION_USER RESET SESSION AUTHORIZATION', 'ALTER ROLE SESSION_USER RESET "session_authorization"'])]
    #[TestWith(['ALTER USER ALL SET SESSION AUTHORIZATION DEFAULT', 'ALTER ROLE ALL SET "session_authorization" = DEFAULT'])]
    #[TestWith(['ALTER ROLE r SET search_path TO app, public', 'ALTER ROLE "r" SET "search_path" = "app", "public"'])]
    #[TestWith(['ALTER ROLE r SET work_mem FROM CURRENT', 'ALTER ROLE "r" SET "work_mem" FROM CURRENT'])]
    #[TestWith(["ALTER ROLE r SET TIME ZONE 'UTC'", 'ALTER ROLE "r" SET "timezone" = \'UTC\''])]
    #[TestWith(['ALTER ROLE r SET NAMES', 'ALTER ROLE "r" SET "names" = DEFAULT'])]
    #[TestWith(['ALTER ROLE r IN DATABASE d RESET timezone', 'ALTER ROLE "r" IN DATABASE "d" RESET "timezone"'])]
    public function testWriteCollapsesSynonymsToCanonicalKeywords(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        $tree = RoleCommands::write($statement);
        self::assertNotNull($tree);
        self::assertSame($expected, $tree->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($statement->kind, $rebound->kind);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testWriteDelegatesPrivilegeOperationsToTheirWriter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $statement = $binder->bind('GRANT SELECT ON t TO a');
        $tree = RoleCommands::write($statement);
        self::assertNotNull($tree);
        self::assertSame('GRANT SELECT ON TABLE "public"."t" TO "a"', $tree->toString());
        self::assertSame($tree->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($tree->toString())));
    }

    public function testSelectionWritesTheRoleAndDatabaseQualifier(): void
    {
        $qualified = RoleCommands::selection(AllRoles::All, 'd');
        self::assertCount(4, $qualified);
        self::assertSame('ALTER ROLE', $qualified[0]->toString());
        self::assertSame('ALL', $qualified[1]->toString());
        self::assertSame('IN DATABASE', $qualified[2]->toString());
        self::assertSame('"d"', $qualified[3]->toString());
        $plain = RoleCommands::selection(new NamedRole('x"y'), null);
        self::assertCount(2, $plain);
        self::assertSame('"x""y"', $plain[1]->toString());
        $session = RoleCommands::selection(SessionRole::CurrentRole, 'My DB');
        self::assertSame('CURRENT_ROLE', $session[1]->toString());
        self::assertSame('"My DB"', $session[3]->toString());
    }
}

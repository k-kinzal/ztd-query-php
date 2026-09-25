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
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Role\RoleOptions;

#[CoversClass(RoleOptions::class)]
#[Medium]
final class RoleOptionsTest extends TestCase
{
    public function testOptionWritesEachOptionWithItsCanonicalKeyword(): void
    {
        self::assertSame('NOLOGIN', RoleOptions::option(new RoleAttribute(RoleCapability::Login, false))->toString());
        self::assertSame('BYPASSRLS', RoleOptions::option(new RoleAttribute(RoleCapability::BypassRls, true))->toString());
        self::assertSame('PASSWORD NULL', RoleOptions::option(new ClearedPassword())->toString());
        self::assertSame('CONNECTION LIMIT -1', RoleOptions::option(new ConnectionLimit(-1))->toString());
        self::assertSame('SYSID 2', RoleOptions::option(new RoleSystemId(2))->toString());
        self::assertSame('ROLE "a"', RoleOptions::option(new RoleMembers([new NamedRole('a')]))->toString());
        self::assertSame('USER "a"', RoleOptions::option(new RoleMembers([new NamedRole('a')]), false)->toString());
        self::assertSame('IN ROLE "a", CURRENT_USER', RoleOptions::option(new RoleMemberships([new NamedRole('a'), SessionRole::CurrentUser]))->toString());
        self::assertSame('ADMIN "x""y"', RoleOptions::option(new RoleAdmins([new NamedRole('x"y')]))->toString());
    }

    public function testOptionWritesPasswordsAndValidityWithTheirSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ROLE r PASSWORD $$secret$$ VALID UNTIL \'infinity\'');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $password = $statement->options[0];
        self::assertInstanceOf(RolePassword::class, $password);
        self::assertSame('PASSWORD $$secret$$', RoleOptions::option($password)->toString());
        $validity = $statement->options[1];
        self::assertInstanceOf(RoleValidity::class, $validity);
        self::assertSame("VALID UNTIL 'infinity'", RoleOptions::option($validity)->toString());
        self::assertSame("VALID UNTIL 'infinity'", RoleOptions::option(new RoleValidity($validity->until), false)->toString());
    }

    #[TestWith(['CREATE USER u WITH ENCRYPTED PASSWORD $$secret$$', 'CREATE USER "u" PASSWORD $$secret$$'])]
    #[TestWith(['CREATE ROLE r IN GROUP a, CURRENT_USER', 'CREATE ROLE "r" IN ROLE "a", CURRENT_USER'])]
    #[TestWith(['CREATE ROLE r WITH "login" CONNECTION LIMIT +5', 'CREATE ROLE "r" LOGIN CONNECTION LIMIT 5'])]
    #[TestWith(['CREATE ROLE r SYSID 0 ROLE b ADMIN d', 'CREATE ROLE "r" SYSID 0 ROLE "b" ADMIN "d"'])]
    public function testOptionCollapsesSynonymsAcrossBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testAlterationWritesMembersWithTheUserKeyword(): void
    {
        $trees = RoleOptions::alteration([new RoleAttribute(RoleCapability::Superuser, true), new RoleMembers([new NamedRole('a'), SessionRole::SessionUser]), new ConnectionLimit(10), new ClearedPassword()]);
        self::assertCount(4, $trees);
        self::assertSame('SUPERUSER', $trees[0]->toString());
        self::assertSame('USER "a", SESSION_USER', $trees[1]->toString());
        self::assertSame('CONNECTION LIMIT 10', $trees[2]->toString());
        self::assertSame('PASSWORD NULL', $trees[3]->toString());
        self::assertSame([], RoleOptions::alteration([]));
    }

    #[TestWith(['ALTER USER CURRENT_USER WITH NOLOGIN USER alice', 'ALTER ROLE CURRENT_USER NOLOGIN USER "alice"'])]
    #[TestWith(["ALTER ROLE r ENCRYPTED PASSWORD 'p' VALID UNTIL '2030-01-01' CONNECTION LIMIT 0", 'ALTER ROLE "r" PASSWORD \'p\' VALID UNTIL \'2030-01-01\' CONNECTION LIMIT 0'])]
    public function testAlterationKeepsAlterationsFixedAcrossBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }
}

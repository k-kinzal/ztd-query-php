<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AddGroupMembersStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Role\RoleSpecs;

#[CoversClass(RoleSpecs::class)]
#[Medium]
final class RoleSpecsTest extends TestCase
{
    public function testRoleQuotesNamesAndWritesKeywordsForSymbolicRoles(): void
    {
        self::assertSame('"x""y"', RoleSpecs::role(new NamedRole('x"y'))->toString());
        self::assertSame('"CURRENT_USER"', RoleSpecs::role(new NamedRole('CURRENT_USER'))->toString());
        self::assertSame('"Public"', RoleSpecs::role(new NamedRole('Public'))->toString());
        self::assertSame('CURRENT_USER', RoleSpecs::role(SessionRole::CurrentUser)->toString());
        self::assertSame('CURRENT_ROLE', RoleSpecs::role(SessionRole::CurrentRole)->toString());
        self::assertSame('SESSION_USER', RoleSpecs::role(SessionRole::SessionUser)->toString());
        self::assertSame('PUBLIC', RoleSpecs::role(PublicRole::Public)->toString());
        self::assertSame('ALL', RoleSpecs::role(AllRoles::All)->toString());
    }

    public function testRolesSeparatesEachRoleWithAComma(): void
    {
        self::assertSame('"a", SESSION_USER, PUBLIC', RoleSpecs::roles([new NamedRole('a'), SessionRole::SessionUser, PublicRole::Public])->toString());
        self::assertSame('"a"', RoleSpecs::roles([new NamedRole('a')])->toString());
    }

    public function testRolesKeepsQuotedSessionWordsDistinctAcrossBinding(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER GROUP staff ADD USER "CURRENT_USER", CURRENT_USER, "Public"');
        self::assertInstanceOf(AddGroupMembersStatement::class, $statement);
        self::assertSame('ALTER GROUP "staff" ADD USER "CURRENT_USER", CURRENT_USER, "Public"', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(AddGroupMembersStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
        self::assertEquals($statement->members, $rebound->members);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleMembers::class)]
#[Medium]
final class RoleMembersTest extends TestCase
{
    public function testRetainsNamedAndSessionRolesInOrder(): void
    {
        $roles = [new NamedRole('alice'), SessionRole::CurrentRole];
        $members = new RoleMembers($roles);
        self::assertSame($roles, $members->roles);
        self::assertSame('alice', $members->roles[0]->name);
    }

    #[TestWith(['ROLE'])]
    #[TestWith(['USER'])]
    public function testRoleAndUserListsBindAsTheSameRequest(string $word): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r ' . $word . ' a, b');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RoleMembers::class, $option);
        self::assertEquals([new NamedRole('a'), new NamedRole('b')], $option->roles);
        self::assertSame('CREATE ROLE "r" ROLE "a", "b"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testAnAlterationWritesTheMembersWithTheUserKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE r USER a');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RoleMembers::class, $option);
        self::assertEquals([new NamedRole('a')], $option->roles);
        self::assertSame('ALTER ROLE "r" USER "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(AlterRoleStatement::class, $rebound);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }
}

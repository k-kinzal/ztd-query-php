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
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleMemberships::class)]
#[Medium]
final class RoleMembershipsTest extends TestCase
{
    public function testRetainsNamedAndSessionRolesInOrder(): void
    {
        $memberships = new RoleMemberships([SessionRole::CurrentUser, new NamedRole('staff')]);
        self::assertSame(SessionRole::CurrentUser, $memberships->roles[0]);
        self::assertSame('CURRENT_USER', $memberships->roles[0]->value);
        self::assertEquals(new NamedRole('staff'), $memberships->roles[1]);
    }

    #[TestWith(['IN ROLE'])]
    #[TestWith(['IN GROUP'])]
    public function testInRoleAndInGroupBindAsTheSameRequest(string $words): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r ' . $words . ' a, SESSION_USER');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RoleMemberships::class, $option);
        self::assertEquals([new NamedRole('a'), SessionRole::SessionUser], $option->roles);
        self::assertSame('CREATE ROLE "r" IN ROLE "a", SESSION_USER', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }
}

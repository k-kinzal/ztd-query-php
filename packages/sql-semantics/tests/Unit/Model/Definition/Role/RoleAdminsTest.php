<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleAdmins::class)]
#[Medium]
final class RoleAdminsTest extends TestCase
{
    public function testRetainsNamedAndSessionRolesInOrder(): void
    {
        $roles = [new NamedRole('ops'), SessionRole::CurrentUser];
        $admins = new RoleAdmins($roles);
        self::assertSame($roles, $admins->roles);
        self::assertSame('ops', $admins->roles[0]->name);
    }

    public function testAdminBindsFromADefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r ADMIN a, CURRENT_USER');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RoleAdmins::class, $option);
        self::assertEquals([new NamedRole('a'), SessionRole::CurrentUser], $option->roles);
        self::assertSame('CREATE ROLE "r" ADMIN "a", CURRENT_USER', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }
}

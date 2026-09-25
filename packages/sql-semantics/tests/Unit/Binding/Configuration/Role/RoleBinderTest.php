<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Role\RoleBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Configuration\Role as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleBinder::class)]
#[Medium]
final class RoleBinderTest extends TestCase
{
    /**
     * @param class-string<BoundStatement> $expected
     */
    #[DataProvider('providerOperations')]
    public function testBindKeepsEachRoleOperationDistinct(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf($expected, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    /**
     * @return iterable<string, array{string, class-string<BoundStatement>}>
     */
    public static function providerOperations(): iterable
    {
        yield 'none' => ['SET ROLE NONE', Statement\SetRolePolicyStatement::class];
        yield 'default' => ['SET ROLE DEFAULT', Statement\SetRolePolicyStatement::class];
        yield 'all' => ['SET ROLE ALL', Statement\SetRolePolicyStatement::class];
        yield 'excluded' => ["SET ROLE ALL EXCEPT 'writer', 'admin'@'localhost'", Statement\SetRolesExceptStatement::class];
        yield 'named none' => ["SET ROLE 'NONE'", Statement\SetExplicitRolesStatement::class];
        yield 'quoted all' => ['SET ROLE `ALL`', Statement\SetExplicitRolesStatement::class];
        yield 'named' => ["SET ROLE reader, 'writer'@'localhost'", Statement\SetExplicitRolesStatement::class];
        yield 'default none' => ["SET DEFAULT ROLE NONE TO 'u'", Statement\SetDefaultRolePolicyStatement::class];
        yield 'default all' => ["SET DEFAULT ROLE ALL TO 'u', 'v'", Statement\SetDefaultRolePolicyStatement::class];
        yield 'default named' => ["SET DEFAULT ROLE 'reader', 'writer' TO 'u', 'v'", Statement\SetDefaultRolesStatement::class];
    }

    public function testBindKeepsUserVariableAssignmentSeparate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @role = 1', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
    }

    public function testBindKeepsRecipientAccountsSeparateFromDefaultRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET DEFAULT ROLE 'reader'@'%' TO 'alice'@'localhost', 'bob'");
        self::assertInstanceOf(Statement\SetDefaultRolesStatement::class, $statement);
        self::assertSame(['reader'], array_column($statement->roles, 'username'));
        self::assertSame(['alice', 'bob'], array_column($statement->accounts, 'username'));
        self::assertSame('localhost', $statement->accounts[0]->host);
        self::assertNull($statement->accounts[1]->host);
    }
}

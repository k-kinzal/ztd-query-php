<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRoleStatement::class)]
#[Medium]
final class AlterRoleStatementTest extends TestCase
{
    public function testReadsTheRoleAndTheOrderedOptionsFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertSame(SessionRole::CurrentUser, $statement->role);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, false), new RoleMembers([new NamedRole('alice')])], $statement->options);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testReadsTheLimitTheValidityAndAClearedPassword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("ALTER ROLE r CONNECTION LIMIT -1 VALID UNTIL 'infinity'");
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertEquals(new NamedRole('r'), $statement->role);
        self::assertInstanceOf(ConnectionLimit::class, $statement->options[0]);
        self::assertSame(-1, $statement->options[0]->limit);
        self::assertInstanceOf(RoleValidity::class, $statement->options[1]);
        self::assertSame("'infinity'", $statement->options[1]->until->text);
        self::assertSame("ALTER ROLE \"r\" CONNECTION LIMIT -1 VALID UNTIL 'infinity'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $cleared = $binder->bind('ALTER ROLE r PASSWORD NULL');
        self::assertInstanceOf(AlterRoleStatement::class, $cleared);
        self::assertEquals([new ClearedPassword()], $cleared->options);
        self::assertSame('ALTER ROLE "r" PASSWORD NULL', (new \SqlSemantics\SimpleSerializer())->serialize($cleared));
    }

    public function testToStringWritesTheRoleKeywordAndTheMembersAsUsers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertSame('ALTER ROLE CURRENT_USER NOLOGIN USER "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(AlterRoleStatement::class, $again);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithRoleReplacesTheAlteredRoleWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $changed = $statement->withRole(new NamedRole('x"y'));
        self::assertNotSame($statement, $changed);
        self::assertSame(SessionRole::CurrentUser, $statement->role);
        self::assertEquals(new NamedRole('x"y'), $changed->role);
        self::assertEquals($statement->options, $changed->options);
        self::assertSame('ALTER ROLE "x""y" NOLOGIN USER "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOptionsReplacesTheCompleteOrderedRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $options = [new RoleAttribute(RoleCapability::Superuser, true), new ConnectionLimit(-1), new ClearedPassword()];
        $changed = $statement->withOptions($options);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, false), new RoleMembers([new NamedRole('alice')])], $statement->options);
        self::assertEquals($options, $changed->options);
        self::assertSame('ALTER ROLE CURRENT_USER SUPERUSER CONNECTION LIMIT -1 PASSWORD NULL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOptionsAcceptsAnEmptyRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $changed = $statement->withOptions([]);
        self::assertSame([], $changed->options);
        self::assertSame('ALTER ROLE CURRENT_USER', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOptionsRejectsContradictoryAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new RoleAttribute(RoleCapability::Login, true), new RoleAttribute(RoleCapability::Login, false)]);
    }

    public function testWithOptionsRejectsARepeatedProperty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new ConnectionLimit(1), new ConnectionLimit(2)]);
    }

    public function testWithOriginRetainsTheRoleAndTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->role, $copy->role);
        self::assertSame($statement->options, $copy->options);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}

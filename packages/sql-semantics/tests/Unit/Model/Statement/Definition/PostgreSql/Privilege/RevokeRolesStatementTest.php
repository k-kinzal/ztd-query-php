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
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeRolesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokeRolesStatement::class)]
#[Medium]
final class RevokeRolesStatementTest extends TestCase
{
    public function testReadsAnOptionRevocationFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertEquals([new NamedRole('staff')], $statement->roles);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertSame(RoleGrantAttribute::Admin, $statement->option);
        self::assertNull($statement->grantor);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
        self::assertSame(StatementKind::Revoke, $statement->kind);
    }

    public function testToStringWritesTheOptionClauseAndThePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertSame('REVOKE ADMIN OPTION FOR "staff" FROM "alice" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT', 'REVOKE ADMIN OPTION FOR "staff" FROM "alice" RESTRICT'])]
    #[TestWith(['REVOKE staff FROM alice, CURRENT_USER GRANTED BY bob', 'REVOKE "staff" FROM "alice", CURRENT_USER GRANTED BY "bob"'])]
    #[TestWith(['REVOKE SET OPTION FOR staff FROM alice CASCADE', 'REVOKE SET OPTION FOR "staff" FROM "alice" CASCADE'])]
    #[TestWith(['REVOKE INHERIT OPTION FOR staff FROM alice', 'REVOKE INHERIT OPTION FOR "staff" FROM "alice"'])]
    public function testRebindingTheOutputReachesAFixedPoint(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(RevokeRolesStatement::class, $again);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithRolesReplacesTheRevokedRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $roles = [new NamedRole('a'), new NamedRole('x"y')];
        $changed = $statement->withRoles($roles);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('staff')], $statement->roles);
        self::assertEquals($roles, $changed->roles);
        self::assertSame('REVOKE ADMIN OPTION FOR "a", "x""y" FROM "alice" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGranteesReplacesTheRolesLosingMembership(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $grantees = [SessionRole::CurrentUser];
        $changed = $statement->withGrantees($grantees);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertEquals($grantees, $changed->grantees);
        self::assertSame('REVOKE ADMIN OPTION FOR "staff" FROM CURRENT_USER RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOptionRemovesOrReplacesTheOptionOnlyRestriction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $membership = $statement->withOption(null);
        self::assertNotSame($statement, $membership);
        self::assertSame(RoleGrantAttribute::Admin, $statement->option);
        self::assertNull($membership->option);
        self::assertSame('REVOKE "staff" FROM "alice" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($membership));
        $set = $statement->withOption(RoleGrantAttribute::Set);
        self::assertSame(RoleGrantAttribute::Set, $set->option);
        self::assertSame('REVOKE SET OPTION FOR "staff" FROM "alice" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($set));
    }

    public function testWithGrantorAddsTheGrantorBeforeThePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $changed = $statement->withGrantor(new NamedRole('bob'));
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->grantor);
        self::assertEquals(new NamedRole('bob'), $changed->grantor);
        self::assertSame('REVOKE ADMIN OPTION FOR "staff" FROM "alice" GRANTED BY "bob" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithBehaviorReplacesTheDependentGrantPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $cascade = $statement->withBehavior(DropBehavior::Cascade);
        self::assertNotSame($statement, $cascade);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
        self::assertSame(DropBehavior::Cascade, $cascade->behavior);
        self::assertSame('REVOKE ADMIN OPTION FOR "staff" FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($cascade));
        self::assertSame('REVOKE ADMIN OPTION FOR "staff" FROM "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withBehavior(DropBehavior::Default)));
    }

    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE staff FROM alice, CURRENT_USER GRANTED BY bob');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
        self::assertSame($statement->grantees, $copy->grantees);
        self::assertSame($statement->option, $copy->option);
        self::assertSame($statement->grantor, $copy->grantor);
        self::assertSame($statement->behavior, $copy->behavior);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAForeignOriginOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new RevokeRolesStatement($origin, [new NamedRole('staff')], [new NamedRole('alice')]);
    }
}

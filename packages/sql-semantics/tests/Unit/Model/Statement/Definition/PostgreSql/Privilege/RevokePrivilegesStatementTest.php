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
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokePrivilegesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokePrivilegesStatement::class)]
#[Medium]
final class RevokePrivilegesStatementTest extends TestCase
{
    public function testReadsAGrantOptionRevocationFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::Execute)], $statement->privileges);
        self::assertInstanceOf(RoutineTargets::class, $statement->target);
        self::assertSame(RoutineClass::Function, $statement->target->class);
        self::assertInstanceOf(RoutineBySignature::class, $statement->target->routines[0]);
        self::assertSame(['f'], $statement->target->routines[0]->name->parts);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertTrue($statement->grantOptionOnly);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(StatementKind::Revoke, $statement->kind);
    }

    public function testToStringKeepsTheSignatureAndTheDependentPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE', 'REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob" CASCADE'])]
    #[TestWith(['REVOKE SELECT (id, a) ON t FROM a RESTRICT', 'REVOKE SELECT ("id", "a") ON TABLE "public"."t" FROM "a" RESTRICT'])]
    #[TestWith(['REVOKE SELECT ON t FROM PUBLIC', 'REVOKE SELECT ON TABLE "public"."t" FROM PUBLIC'])]
    #[TestWith(['REVOKE USAGE ON SCHEMA s FROM CURRENT_USER', 'REVOKE USAGE ON SCHEMA "s" FROM CURRENT_USER'])]
    public function testRebindingTheOutputReachesAFixedPoint(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(RevokePrivilegesStatement::class, $again);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithPrivilegesReplacesTheCompleteRequestWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $privileges = [new ObjectPrivilege(Privilege::All)];
        $changed = $statement->withPrivileges($privileges);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new ObjectPrivilege(Privilege::Execute)], $statement->privileges);
        self::assertEquals($privileges, $changed->privileges);
        self::assertSame('REVOKE GRANT OPTION FOR ALL PRIVILEGES ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithPrivilegesRejectsAPrivilegeOutsideTheRoutineDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPrivileges([new ObjectPrivilege(Privilege::Select)]);
    }

    public function testWithTargetReplacesTheObjectSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $target = new RoutineTargets(RoutineClass::Procedure, [new RoutineByName(new QualifiedName(['p']))]);
        $changed = $statement->withTarget($target);
        self::assertNotSame($statement, $changed);
        self::assertEquals($target, $changed->target);
        self::assertEquals($statement->privileges, $changed->privileges);
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON PROCEDURE "p" FROM "alice" GRANTED BY "bob" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTargetRejectsAClassOutsideThePrivilegeDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTarget(new LargeObjectTargets([12]));
    }

    public function testWithGranteesReplacesTheRolesLosingThePrivileges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $grantees = [PublicRole::Public, SessionRole::SessionUser];
        $changed = $statement->withGrantees($grantees);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertEquals($grantees, $changed->grantees);
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM PUBLIC, SESSION_USER GRANTED BY "bob" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGrantOptionOnlyRevokesThePrivilegeItself(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $changed = $statement->withGrantOptionOnly(false);
        self::assertNotSame($statement, $changed);
        self::assertTrue($statement->grantOptionOnly);
        self::assertFalse($changed->grantOptionOnly);
        self::assertSame('REVOKE EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGrantorRemovesOrReplacesTheGrantor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $removed = $statement->withGrantor(null);
        self::assertNotSame($statement, $removed);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertNull($removed->grantor);
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($removed));
        $session = $statement->withGrantor(SessionRole::CurrentRole);
        self::assertSame(SessionRole::CurrentRole, $session->grantor);
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY CURRENT_ROLE CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($session));
    }

    public function testWithBehaviorReplacesTheDependentGrantPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $restricted = $statement->withBehavior(DropBehavior::Restrict);
        self::assertNotSame($statement, $restricted);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(DropBehavior::Restrict, $restricted->behavior);
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($restricted));
        self::assertSame('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION "f"(integer) FROM "alice" GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withBehavior(DropBehavior::Default)));
    }

    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->privileges, $copy->privileges);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->grantees, $copy->grantees);
        self::assertSame($statement->grantOptionOnly, $copy->grantOptionOnly);
        self::assertSame($statement->grantor, $copy->grantor);
        self::assertSame($statement->behavior, $copy->behavior);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
        self::assertInstanceOf(RevokePrivilegesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAllPrivilegesCombinedWithAnotherOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $target = new LargeObjectTargets([12, 13]);
        $this->expectException(InvalidStructure::class);
        new RevokePrivilegesStatement($origin, [new ObjectPrivilege(Privilege::All), new ObjectPrivilege(Privilege::Select)], $target, [PublicRole::Public]);
    }
}

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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropGroupMembersStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropGroupMembersStatement::class)]
#[Medium]
final class DropGroupMembersStatementTest extends TestCase
{
    public function testReadsTheGroupAndItsMembersFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        self::assertInstanceOf(DropGroupMembersStatement::class, $statement);
        self::assertEquals(new NamedRole('staff'), $statement->group);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser], $statement->members);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testToStringQuotesTheGroupAndEachNamedMember(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        self::assertSame('ALTER GROUP "staff" DROP USER "alice", CURRENT_USER', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(DropGroupMembersStatement::class, $again);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithGroupReplacesTheGroupWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        self::assertInstanceOf(DropGroupMembersStatement::class, $statement);
        $changed = $statement->withGroup(SessionRole::SessionUser);
        self::assertNotSame($statement, $changed);
        self::assertEquals(new NamedRole('staff'), $statement->group);
        self::assertSame(SessionRole::SessionUser, $changed->group);
        self::assertEquals($statement->members, $changed->members);
        self::assertSame('ALTER GROUP SESSION_USER DROP USER "alice", CURRENT_USER', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithMembersReplacesAndQuotesTheCompleteSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        self::assertInstanceOf(DropGroupMembersStatement::class, $statement);
        $members = [new NamedRole('x"y'), SessionRole::CurrentRole];
        $changed = $statement->withMembers($members);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser], $statement->members);
        self::assertEquals($members, $changed->members);
        self::assertSame('ALTER GROUP "staff" DROP USER "x""y", CURRENT_ROLE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheGroupAndTheMembers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        self::assertInstanceOf(DropGroupMembersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertEquals($statement->group, $copy->group);
        self::assertEquals($statement->members, $copy->members);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
        self::assertInstanceOf(DropGroupMembersStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAForeignOriginOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new DropGroupMembersStatement($origin, new NamedRole('staff'), [new NamedRole('alice')]);
    }
}

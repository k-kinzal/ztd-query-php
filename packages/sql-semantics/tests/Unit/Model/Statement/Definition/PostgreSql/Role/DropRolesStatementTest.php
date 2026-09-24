<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropRolesStatement::class)]
#[Medium]
final class DropRolesStatementTest extends TestCase
{
    public function testReadsTheSelectionAndTheExistencePolicyFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        self::assertTrue($statement->ifExists);
        self::assertEquals([new NamedRole('alice'), new NamedRole('bob')], $statement->roles);
        self::assertSame(StatementKind::Drop, $statement->kind);
    }

    public function testToStringWritesTheRoleKeywordAndQuotesEachName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
        self::assertSame('DROP ROLE IF EXISTS "alice", "bob"', $statement->toString());
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP GROUP IF EXISTS alice, bob');
        $again = $binder->bind($statement->toString());
        self::assertInstanceOf(DropRolesStatement::class, $again);
        self::assertSame($statement->toString(), $again->toString());
    }

    public function testWithRolesReplacesAndQuotesTheCompleteSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        $roles = [new NamedRole('x"y')];
        $changed = $statement->withRoles($roles);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice'), new NamedRole('bob')], $statement->roles);
        self::assertEquals($roles, $changed->roles);
        self::assertSame('DROP ROLE IF EXISTS "x""y"', $changed->toString());
    }

    public function testWithIfExistsChangesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertTrue($statement->ifExists);
        self::assertFalse($changed->ifExists);
        self::assertEquals($statement->roles, $changed->roles);
        self::assertSame('DROP ROLE "alice", "bob"', $changed->toString());
    }

    public function testWithOriginRetainsTheSelectionAndThePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertEquals($statement->roles, $copy->roles);
        self::assertSame($statement->ifExists, $copy->ifExists);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
        self::assertInstanceOf(DropRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAForeignOriginOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new DropRolesStatement($origin, [new NamedRole('alice')]);
    }
}

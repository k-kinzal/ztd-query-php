<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\RenameRoleStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameRoleStatement::class)]
#[Medium]
final class RenameRoleStatementTest extends TestCase
{
    public function testReadsBothNamesFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
        self::assertInstanceOf(RenameRoleStatement::class, $statement);
        self::assertEquals(new NamedRole('staff'), $statement->role);
        self::assertEquals(new NamedRole('crew'), $statement->newName);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testToStringWritesTheRoleKeywordAndQuotesBothNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
        self::assertSame('ALTER ROLE "staff" RENAME TO "crew"', $statement->toString());
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER USER staff RENAME TO crew');
        $again = $binder->bind($statement->toString());
        self::assertInstanceOf(RenameRoleStatement::class, $again);
        self::assertSame($statement->toString(), $again->toString());
    }

    public function testWithRoleReplacesTheRenamedRoleWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
        self::assertInstanceOf(RenameRoleStatement::class, $statement);
        $changed = $statement->withRole(new NamedRole('x"y'));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new NamedRole('staff'), $statement->role);
        self::assertEquals(new NamedRole('x"y'), $changed->role);
        self::assertEquals($statement->newName, $changed->newName);
        self::assertSame('ALTER ROLE "x""y" RENAME TO "crew"', $changed->toString());
    }

    public function testWithNewNameKeepsAQuotedSpecialWordAsANamedRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
        self::assertInstanceOf(RenameRoleStatement::class, $statement);
        $changed = $statement->withNewName(new NamedRole('CURRENT_USER'));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new NamedRole('crew'), $statement->newName);
        self::assertEquals(new NamedRole('CURRENT_USER'), $changed->newName);
        self::assertEquals($statement->role, $changed->role);
        self::assertSame('ALTER ROLE "staff" RENAME TO "CURRENT_USER"', $changed->toString());
    }

    public function testWithOriginRetainsBothNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
        self::assertInstanceOf(RenameRoleStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertEquals($statement->role, $copy->role);
        self::assertEquals($statement->newName, $copy->newName);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
        self::assertInstanceOf(RenameRoleStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAForeignOriginOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new RenameRoleStatement($origin, new NamedRole('staff'), new NamedRole('crew'));
    }
}

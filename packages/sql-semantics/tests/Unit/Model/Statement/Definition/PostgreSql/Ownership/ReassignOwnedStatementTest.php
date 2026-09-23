<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReassignOwnedStatement::class)]
#[Medium]
final class ReassignOwnedStatementTest extends TestCase
{
    public function testWithOwnersReplacesAndQuotesTheCompleteSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY alice TO SESSION_USER');
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        $owners = [SessionRole::CurrentUser, new NamedRole('x"y')];
        $changed = $statement->withOwners($owners);
        self::assertEquals([new NamedRole('alice')], $statement->owners);
        self::assertEquals($owners, $changed->owners);
        self::assertStringContainsString('BY CURRENT_USER, "x""y"', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithOriginRetainsTheOperationAndItsOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY alice TO SESSION_USER');
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertEquals($statement->owners, $copy->owners);
    }

    public function testWithOriginRejectsAnIncompatibleDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY alice TO SESSION_USER');
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNewOwnerKeepsQuotedNamesSeparateFromSessionRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY alice TO SESSION_USER');
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        $changed = $statement->withNewOwner(new NamedRole('SESSION_USER'));
        self::assertSame(SessionRole::SessionUser, $statement->newOwner);
        self::assertEquals(new NamedRole('SESSION_USER'), $changed->newOwner);
        self::assertEquals($statement->owners, $changed->owners);
        self::assertSame('REASSIGN OWNED BY "alice" TO "SESSION_USER"', $changed->toString());
    }

}

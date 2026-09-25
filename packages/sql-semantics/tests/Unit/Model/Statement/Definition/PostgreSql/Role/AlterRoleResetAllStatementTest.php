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
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetAllStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRoleResetAllStatement::class)]
#[Medium]
final class AlterRoleResetAllStatementTest extends TestCase
{
    public function testReadsTheRoleAndTheDatabaseFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        self::assertEquals(new NamedRole('app'), $statement->role);
        self::assertSame('shop', $statement->database);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testReadsTheAllRolesSelectionWithoutADatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER ALL RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        self::assertSame(AllRoles::All, $statement->role);
        self::assertNull($statement->database);
        self::assertSame('ALTER ROLE ALL RESET ALL', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testToStringQuotesTheRoleAndTheDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertSame('ALTER ROLE "app" IN DATABASE "shop" RESET ALL', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $again);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithRoleReplacesTheSelectionWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $changed = $statement->withRole(AllRoles::All);
        self::assertNotSame($statement, $changed);
        self::assertEquals(new NamedRole('app'), $statement->role);
        self::assertSame(AllRoles::All, $changed->role);
        self::assertSame('shop', $changed->database);
        self::assertSame('ALTER ROLE ALL IN DATABASE "shop" RESET ALL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithRoleAcceptsASessionRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $changed = $statement->withRole(SessionRole::CurrentUser);
        self::assertSame(SessionRole::CurrentUser, $changed->role);
        self::assertSame('ALTER ROLE CURRENT_USER IN DATABASE "shop" RESET ALL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithDatabaseRemovesTheQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $changed = $statement->withDatabase(null);
        self::assertNotSame($statement, $changed);
        self::assertSame('shop', $statement->database);
        self::assertNull($changed->database);
        self::assertEquals($statement->role, $changed->role);
        self::assertSame('ALTER ROLE "app" RESET ALL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithDatabaseQuotesTheReplacementName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $changed = $statement->withDatabase('x"y');
        self::assertNull($statement->database);
        self::assertSame('x"y', $changed->database);
        self::assertSame('ALTER ROLE "app" IN DATABASE "x""y" RESET ALL', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithDatabaseRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDatabase('');
    }

    public function testWithOriginRetainsTheRoleAndTheDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertEquals($statement->role, $copy->role);
        self::assertSame($statement->database, $copy->database);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app IN DATABASE shop RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}

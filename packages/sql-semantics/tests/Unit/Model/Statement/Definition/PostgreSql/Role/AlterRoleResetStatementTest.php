<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRoleResetStatement::class)]
#[Medium]
final class AlterRoleResetStatementTest extends TestCase
{
    public function testReadsTheRoleAndTheRemovedDefaultFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        self::assertEquals(new NamedRole('app'), $statement->role);
        self::assertNull($statement->database);
        self::assertSame(['timezone'], $statement->setting->name);
        self::assertSame(SettingScope::Session, $statement->setting->scope);
        self::assertFalse($statement->setting->ifExists);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testReadsTheAllRolesSelectionWithADatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE d RESET work_mem');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        self::assertSame(AllRoles::All, $statement->role);
        self::assertSame('d', $statement->database);
        self::assertSame(['work_mem'], $statement->setting->name);
        self::assertSame('ALTER ROLE ALL IN DATABASE "d" RESET "work_mem"', $statement->toString());
    }

    public function testToStringQuotesTheRoleAndTheCanonicalParameterName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
        self::assertSame('ALTER ROLE "app" RESET "timezone"', $statement->toString());
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER USER app IN DATABASE d RESET TIME ZONE');
        $again = $binder->bind($statement->toString());
        self::assertInstanceOf(AlterRoleResetStatement::class, $again);
        self::assertSame($statement->toString(), $again->toString());
    }

    public function testWithRoleReplacesTheSelectionWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $changed = $statement->withRole(SessionRole::CurrentRole);
        self::assertNotSame($statement, $changed);
        self::assertEquals(new NamedRole('app'), $statement->role);
        self::assertSame(SessionRole::CurrentRole, $changed->role);
        self::assertSame($statement->setting->name, $changed->setting->name);
        self::assertSame('ALTER ROLE CURRENT_ROLE RESET "timezone"', $changed->toString());
    }

    public function testWithDatabaseAddsAQuotedQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $changed = $statement->withDatabase('x"y');
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->database);
        self::assertSame('x"y', $changed->database);
        self::assertSame('ALTER ROLE "app" IN DATABASE "x""y" RESET "timezone"', $changed->toString());
    }

    public function testWithDatabaseRemovesTheQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE d RESET work_mem');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $changed = $statement->withDatabase(null);
        self::assertSame('d', $statement->database);
        self::assertNull($changed->database);
        self::assertSame('ALTER ROLE ALL RESET "work_mem"', $changed->toString());
    }

    public function testWithDatabaseRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDatabase('');
    }

    public function testWithSettingReplacesTheResetParameter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $reset = $binder->bind('RESET work_mem');
        self::assertInstanceOf(ResetSettingStatement::class, $reset);
        $changed = $statement->withSetting($reset->setting);
        self::assertNotSame($statement, $changed);
        self::assertSame(['timezone'], $statement->setting->name);
        self::assertSame($reset->setting->name, $changed->setting->name);
        self::assertEquals($statement->role, $changed->role);
        self::assertSame('ALTER ROLE "app" RESET "work_mem"', $changed->toString());
    }

    public function testWithSettingRejectsALocalScope(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $local = new ResetSetting(['work_mem'], SettingScope::Local, $statement->setting->source);
        $this->expectException(InvalidStructure::class);
        $statement->withSetting($local);
    }

    public function testWithSettingRejectsAnExistenceQualifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $guarded = new ResetSetting(['work_mem'], SettingScope::Session, $statement->setting->source, true);
        $this->expectException(InvalidStructure::class);
        $statement->withSetting($guarded);
    }

    public function testWithOriginRetainsTheRoleTheDatabaseAndTheSetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE d RESET work_mem');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->role, $copy->role);
        self::assertSame($statement->database, $copy->database);
        self::assertSame($statement->setting, $copy->setting);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER app RESET TIME ZONE');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}

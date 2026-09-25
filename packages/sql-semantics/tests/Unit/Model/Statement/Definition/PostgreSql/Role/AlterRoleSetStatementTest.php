<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleSetStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterRoleSetStatement::class)]
#[Medium]
final class AlterRoleSetStatementTest extends TestCase
{
    public function testReadsTheSelectionTheDatabaseAndTheAssignmentFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        self::assertSame(AllRoles::All, $statement->role);
        self::assertSame('app', $statement->database);
        self::assertInstanceOf(AssignedSetting::class, $statement->setting);
        self::assertSame(['search_path'], $statement->setting->name);
        self::assertSame(SettingScope::Session, $statement->setting->scope);
        self::assertCount(2, $statement->setting->values);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testReadsADefaultAndACurrentAssignment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $default = $binder->bind('ALTER ROLE app SET work_mem TO DEFAULT');
        self::assertInstanceOf(AlterRoleSetStatement::class, $default);
        self::assertInstanceOf(DefaultSetting::class, $default->setting);
        self::assertSame('ALTER ROLE "app" SET "work_mem" = DEFAULT', (new \SqlSemantics\SimpleSerializer())->serialize($default));
        $current = $binder->bind('ALTER USER SESSION_USER SET work_mem FROM CURRENT');
        self::assertInstanceOf(AlterRoleSetStatement::class, $current);
        self::assertInstanceOf(CurrentSetting::class, $current->setting);
        self::assertSame(SessionRole::SessionUser, $current->role);
        self::assertSame('ALTER ROLE SESSION_USER SET "work_mem" FROM CURRENT', (new \SqlSemantics\SimpleSerializer())->serialize($current));
    }

    public function testToStringQuotesTheDatabaseTheParameterAndTheValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        self::assertSame('ALTER ROLE ALL IN DATABASE "app" SET "search_path" = "app", "public"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(AlterRoleSetStatement::class, $again);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithRoleReplacesTheSelectionWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $changed = $statement->withRole(new NamedRole('x"y'));
        self::assertNotSame($statement, $changed);
        self::assertSame(AllRoles::All, $statement->role);
        self::assertEquals(new NamedRole('x"y'), $changed->role);
        self::assertSame($statement->setting->name, $changed->setting->name);
        self::assertSame('ALTER ROLE "x""y" IN DATABASE "app" SET "search_path" = "app", "public"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithDatabaseRemovesTheQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $changed = $statement->withDatabase(null);
        self::assertNotSame($statement, $changed);
        self::assertSame('app', $statement->database);
        self::assertNull($changed->database);
        self::assertSame('ALTER ROLE ALL SET "search_path" = "app", "public"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithDatabaseQuotesTheReplacementName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app SET work_mem = 1');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $changed = $statement->withDatabase('shop');
        self::assertNull($statement->database);
        self::assertSame('shop', $changed->database);
        self::assertSame('ALTER ROLE "app" IN DATABASE "shop" SET "work_mem" = 1', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithDatabaseRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app SET work_mem = 1');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDatabase('');
    }

    public function testWithSettingReplacesTheStoredAssignment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $set = $binder->bind('SET work_mem TO DEFAULT');
        self::assertInstanceOf(SetStatement::class, $set);
        $setting = $set->settings[0];
        self::assertInstanceOf(DefaultSetting::class, $setting);
        $changed = $statement->withSetting($setting);
        self::assertNotSame($statement, $changed);
        self::assertSame(['search_path'], $statement->setting->name);
        self::assertSame($setting->name, $changed->setting->name);
        self::assertInstanceOf(DefaultSetting::class, $changed->setting);
        self::assertSame('ALTER ROLE ALL IN DATABASE "app" SET "work_mem" = DEFAULT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithSettingRejectsALocalScope(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE app SET work_mem = 1');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $set = $binder->bind('SET LOCAL work_mem TO 1');
        self::assertInstanceOf(SetStatement::class, $set);
        $local = $set->settings[0];
        self::assertInstanceOf(AssignedSetting::class, $local);
        $this->expectException(InvalidStructure::class);
        $statement->withSetting($local);
    }

    public function testWithOriginRetainsTheSelectionTheDatabaseAndTheSetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE app SET search_path TO app, public');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->role, $copy->role);
        self::assertSame($statement->database, $copy->database);
        self::assertSame($statement->setting, $copy->setting);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE app SET work_mem = 1');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}

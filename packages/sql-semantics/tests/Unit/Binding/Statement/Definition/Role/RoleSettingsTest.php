<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetAllStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleSetStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\RoleSettings::class)]
#[Medium]
final class RoleSettingsTest extends TestCase
{
    public function testBindReadsAnAssignedSetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE r SET search_path TO app, public');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        self::assertEquals(new NamedRole('r'), $statement->role);
        self::assertNull($statement->database);
        $setting = $statement->setting;
        self::assertInstanceOf(AssignedSetting::class, $setting);
        self::assertSame(['search_path'], $setting->name);
        self::assertCount(2, $setting->values);
        self::assertSame(SettingScope::Session, $setting->scope);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testBindReadsTimeZoneAsItsParameterName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER USER r SET TIME ZONE 'UTC'");
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        $setting = $statement->setting;
        self::assertInstanceOf(AssignedSetting::class, $setting);
        self::assertSame(['timezone'], $setting->name);
        self::assertCount(1, $setting->values);
    }

    /**
     * @param class-string<CurrentSetting|DefaultSetting> $class
     */
    #[TestWith(['ALTER ROLE r SET work_mem FROM CURRENT', CurrentSetting::class, 'work_mem'])]
    #[TestWith(['ALTER ROLE r SET work_mem TO DEFAULT', DefaultSetting::class, 'work_mem'])]
    #[TestWith(['ALTER ROLE r SET NAMES', DefaultSetting::class, 'names'])]
    #[TestWith(['ALTER USER ALL SET SESSION AUTHORIZATION DEFAULT', DefaultSetting::class, 'session_authorization'])]
    public function testBindReadsCurrentAndDefaultSettings(string $sql, string $class, string $name): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        self::assertInstanceOf($class, $statement->setting);
        self::assertSame([$name], $statement->setting->name);
        self::assertSame(SettingScope::Session, $statement->setting->scope);
    }

    public function testBindReadsAResetOfOneParameter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE r IN DATABASE d RESET timezone');
        self::assertInstanceOf(AlterRoleResetStatement::class, $statement);
        self::assertEquals(new NamedRole('r'), $statement->role);
        self::assertSame('d', $statement->database);
        self::assertSame(['timezone'], $statement->setting->name);
        self::assertFalse($statement->setting->ifExists);
        self::assertSame(StatementKind::Alter, $statement->kind);
        $authorization = $binder->bind('ALTER ROLE SESSION_USER RESET SESSION AUTHORIZATION');
        self::assertInstanceOf(AlterRoleResetStatement::class, $authorization);
        self::assertSame(SessionRole::SessionUser, $authorization->role);
        self::assertSame(['session_authorization'], $authorization->setting->name);
    }

    public function testBindReadsAResetOfEverySetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE ALL IN DATABASE d RESET ALL');
        self::assertInstanceOf(AlterRoleResetAllStatement::class, $statement);
        self::assertSame(AllRoles::All, $statement->role);
        self::assertSame('d', $statement->database);
        self::assertSame(StatementKind::Alter, $statement->kind);
    }

    public function testBindReadsTheRoleSelectionAndDatabaseQualifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $all = $binder->bind('ALTER USER ALL RESET work_mem');
        self::assertInstanceOf(AlterRoleResetStatement::class, $all);
        self::assertSame(AllRoles::All, $all->role);
        self::assertNull($all->database);
        $qualified = $binder->bind('ALTER ROLE "CURRENT_USER" IN DATABASE "My DB" SET work_mem = \'1MB\'');
        self::assertInstanceOf(AlterRoleSetStatement::class, $qualified);
        self::assertEquals(new NamedRole('CURRENT_USER'), $qualified->role);
        self::assertSame('My DB', $qualified->database);
    }

    #[TestWith(['ALTER ROLE r SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'])]
    #[TestWith(['ALTER ROLE r SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY'])]
    public function testBindRejectsTransactionCharacteristics(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleSetting->message());
        $binder->bind($sql);
    }

    public function testBindRejectsPublicAsTheRole(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind('ALTER ROLE public SET work_mem TO 1');
    }
}

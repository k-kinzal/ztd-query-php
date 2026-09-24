<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Settings;

#[CoversClass(Settings::class)]
#[Medium]
final class SettingsTest extends TestCase
{
    #[TestWith([Dialect::Sqlite, 'PRAGMA cache_size', 'PRAGMA "cache_size"'])]
    #[TestWith([Dialect::Sqlite, 'PRAGMA main.cache_size = -2000', 'PRAGMA "main"."cache_size" = - 2000'])]
    #[TestWith([Dialect::Sqlite, "PRAGMA journal_mode = 'wal'", "PRAGMA \"journal_mode\" = 'wal'"])]
    #[TestWith([Dialect::Sqlite, 'PRAGMA journal_mode = wal', 'PRAGMA "journal_mode" = "wal"'])]
    #[TestWith([Dialect::Sqlite, 'PRAGMA cache_size(+10)', 'PRAGMA "cache_size" = + 10'])]
    #[TestWith([Dialect::PostgreSql, 'RESET ALL', 'RESET ALL'])]
    #[TestWith([Dialect::PostgreSql, 'RESET search_path', 'RESET "search_path"'])]
    #[TestWith([Dialect::MySql, 'RESET PERSIST', 'RESET PERSIST'])]
    #[TestWith([Dialect::MySql, 'RESET PERSIST IF EXISTS max_connections', 'RESET PERSIST IF EXISTS `max_connections`'])]
    #[TestWith([Dialect::MySql, 'SET @x = 1, @y = 2', 'SET @`x` = 1, @`y` = 2'])]
    #[TestWith([Dialect::MySql, 'SET GLOBAL max_connections = 10, SESSION sql_mode = DEFAULT, PERSIST wait_timeout = 5, PERSIST_ONLY x = 1', 'SET GLOBAL `max_connections` = 10, SESSION `sql_mode` = DEFAULT, PERSIST `wait_timeout` = 5, PERSIST_ONLY `x` = 1'])]
    #[TestWith([Dialect::MySql, 'SET @@global.max_connections = 10, @@session.sql_mode = DEFAULT', 'SET GLOBAL `max_connections` = 10, SESSION `sql_mode` = DEFAULT'])]
    #[TestWith([Dialect::PostgreSql, 'SET search_path TO a, "B", 1, \'c\', 1.5', 'SET "search_path" = "a", "B", 1, \'c\', 1.5'])]
    #[TestWith([Dialect::PostgreSql, 'SET LOCAL search_path = DEFAULT', 'SET LOCAL "search_path" = DEFAULT'])]
    #[TestWith([Dialect::PostgreSql, 'SET SESSION search_path FROM CURRENT', 'SET "search_path" FROM CURRENT'])]
    #[TestWith([Dialect::PostgreSql, "SET TIME ZONE 'UTC'", "SET \"timezone\" = 'UTC'"])]
    #[TestWith([Dialect::PostgreSql, "SET TIME ZONE INTERVAL '1' HOUR", "SET TIME ZONE INTERVAL '1' HOUR"])]
    #[TestWith([Dialect::PostgreSql, "SET TIME ZONE INTERVAL '1' SECOND(2)", "SET TIME ZONE INTERVAL '1' SECOND(2)"])]
    public function testWriteSerializesEachConfigurationFormFromItsOperands(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf(ConfigurationStatement::class, $statement);
        self::assertSame($expected, Settings::write($statement)->toString());
        $rebound = $binder->bind($expected, strict: false);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteKeepsTheResetScopeOfAReboundSetting(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('RESET PERSIST IF EXISTS max_connections');
        self::assertInstanceOf(ResetSettingStatement::class, $statement);
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(ResetSettingStatement::class, $rebound);
        self::assertTrue($rebound->setting->ifExists);
        self::assertSame(['max_connections'], $rebound->setting->name);
    }

    public function testAssignmentWritesEachSettingForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET GLOBAL max_connections = 10, SESSION sql_mode = DEFAULT, @x = 1', strict: false);
        self::assertInstanceOf(SetStatement::class, $statement);
        self::assertSame('GLOBAL `max_connections` = 10', Settings::assignment($statement->settings[0], Dialect::MySql)->toString());
        self::assertSame('`sql_mode` = DEFAULT', Settings::assignment($statement->settings[1], Dialect::MySql)->toString());
        self::assertSame('SESSION `sql_mode` = DEFAULT', Settings::assignment($statement->settings[1], Dialect::MySql, \SqlSemantics\Model\Configuration\SettingScope::Global)->toString());
        self::assertSame('@`x` = 1', Settings::assignment($statement->settings[2], Dialect::MySql)->toString());
    }

    public function testPragmaValueWritesSignedNumbersWithoutExpressionParentheses(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('PRAGMA main.cache_size = -2000');
        self::assertInstanceOf(AssignPragmaStatement::class, $statement);
        self::assertSame('- 2000', Settings::pragmaValue($statement->value, Dialect::Sqlite)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(AssignPragmaStatement::class, $rebound);
        self::assertSame($statement->value::class, $rebound->value::class);
    }

    public function testIntervalZoneIgnoresAnythingButAnIntervalLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TIME ZONE INTERVAL(3) '1'");
        self::assertInstanceOf(SetStatement::class, $statement);
        $setting = $statement->settings[0];
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $setting);
        self::assertSame("INTERVAL(3) '1'", Settings::intervalZone($setting->values[0])?->toString());
        self::assertNull(Settings::intervalZone(\SqlSemantics\Model\Expression::literal('UTC', Dialect::PostgreSql)));
    }
}

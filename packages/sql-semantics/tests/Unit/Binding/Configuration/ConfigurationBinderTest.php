<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\ConfigurationBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConfigurationBinder::class)]
#[Medium]
final class ConfigurationBinderTest extends TestCase
{
    public function testBindCollectsEachAssignmentOfAMySqlSetList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET SESSION sql_mode = 'x', @b := 2");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertCount(2, $statement->settings);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertSame(['sql_mode'], $statement->settings[0]->name);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[1]);
        self::assertSame(['b'], $statement->settings[1]->name);
        self::assertSame("SET `sql_mode` = 'x', @`b` = 2", $statement->toString());
    }

    public function testBindRoutesTransactionSettingsBeforeOrdinaryAssignments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Transaction\SetCurrentTransactionStatement::class, $statement);
        self::assertSame([\SqlSemantics\Model\Transaction\Isolation::Serializable], $statement->modes);
    }

    public function testBindRoutesPasswordAndRoleForms(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement::class, $binder->bind("SET PASSWORD = 'x'"));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolePolicyStatement::class, $binder->bind('SET DEFAULT ROLE ALL TO u'));
    }

    public function testBindKeepsDefaultAndCurrentValueCopiesAsSettings(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $default = $binder->bind('SET x = DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $default);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\DefaultSetting::class, $default->settings[0]);
        $current = $binder->bind('SET x FROM CURRENT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $current);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\CurrentSetting::class, $current->settings[0]);
    }

    public function testBindRoutesResetToItsOwnBinder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetAllSettingsStatement::class, $binder->bind('RESET ALL'));
        $named = $binder->bind('RESET my.setting');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetSettingStatement::class, $named);
        self::assertSame(['my', 'setting'], $named->setting->name);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\SettingAction;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SettingAction::class)]
#[Medium]
final class SettingActionTest extends TestCase
{
    public function testRepresentsEveryConfigurationEffect(): void
    {
        self::assertSame(['default', 'set', 'reset', 'read', 'from-current'], array_column(SettingAction::cases(), 'value'));
    }

    public function testDerivesTheEffectFromTheBoundSettingForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $assigned = $binder->bind("SET SESSION work_mem = '4MB'");
        self::assertInstanceOf(SetStatement::class, $assigned);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $assigned->settings[0]);
        self::assertSame(SettingAction::Assign, $assigned->settings[0]->action);
        $reset = $binder->bind('RESET work_mem');
        self::assertInstanceOf(ResetSettingStatement::class, $reset);
        self::assertSame(SettingAction::Reset, $reset->setting->action);
    }
}

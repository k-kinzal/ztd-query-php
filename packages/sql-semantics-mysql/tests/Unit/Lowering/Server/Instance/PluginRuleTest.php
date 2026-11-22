<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\PluginRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PluginRule::class)]
#[Medium]
final class PluginRuleTest extends TestCase
{
    public function testStatementLowersComponents(): void
    {
        self::assertSame("UNINSTALL COMPONENT 'a', 'b'", (new Inspection())->inspect("uninstall component 'a', 'b'")->toString());
    }

    public function testSettingsLowersTheList(): void
    {
        self::assertSame("INSTALL COMPONENT 'a' SET a.x = 1, GLOBAL a.y = ON", (new Inspection())->inspect("install component 'a' set a.x = 1, global a.y = on")->toString());
    }

    public function testSettingLowersPersist(): void
    {
        self::assertSame("INSTALL COMPONENT 'a' SET PERSIST x = 2", (new Inspection('mysql-9.1.0'))->inspect("install component 'a' set persist x := 2")->toString());
    }
}

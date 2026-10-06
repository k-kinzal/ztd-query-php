<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\PluginRule;

#[CoversClass(PluginRule::class)]
#[Medium]
final class PluginRuleTest extends TestCase
{
    public function testStatementLowersComponents(): void
    {
        self::assertSame("UNINSTALL COMPONENT 'a', 'b'", (new Semantics(Dialect::MySql))->analyze("uninstall component 'a', 'b'")->toString());
    }

    public function testSettingsLowersTheList(): void
    {
        self::assertSame("INSTALL COMPONENT 'a' SET a.x = 1, GLOBAL a.y = ON", (new Semantics(Dialect::MySql))->analyze("install component 'a' set a.x = 1, global a.y = on")->toString());
    }

    public function testSettingLowersPersist(): void
    {
        self::assertSame("INSTALL COMPONENT 'a' SET PERSIST x = 2", (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze("install component 'a' set persist x := 2")->toString());
    }
}

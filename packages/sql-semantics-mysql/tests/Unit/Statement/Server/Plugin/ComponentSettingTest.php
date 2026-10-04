<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting;

#[CoversClass(ComponentSetting::class)]
#[Medium]
final class ComponentSettingTest extends TestCase
{
    public function testRenderWritesScopeVariableAndValue(): void
    {
        self::assertSame("INSTALL COMPONENT 'c' SET GLOBAL v = 'x'", (new Semantics(Dialect::MySql))->analyze("install component 'c' set global v = 'x'")->toString());
    }
}

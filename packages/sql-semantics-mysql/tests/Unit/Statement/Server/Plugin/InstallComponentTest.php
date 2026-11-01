<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(InstallComponent::class)]
#[Medium]
final class InstallComponentTest extends TestCase
{
    public function testRenderWritesTheComponents(): void
    {
        self::assertSame("INSTALL COMPONENT 'a', 'b'", (new Semantics(Dialect::MySql))->analyze("install component 'a', 'b'")->toString());
    }

    public function testRenderRejectsARadixComponentName(): void
    {
        $this->expectExceptionMessage('A component URN is a quoted string.');
        new InstallComponent([new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', radix: \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal)]);
    }

    public function testDeriveStatementDerivesTheVariablesAndValues(): void
    {
        $install = (new Semantics(Dialect::MySql))->analyze("INSTALL COMPONENT 'a' SET PERSIST a.v = 1");
        self::assertInstanceOf(InstallComponent::class, $install);

        self::assertInstanceOf(Dependent::class, $install->settings[0]->variable->scalarInformation()->type);
        self::assertInstanceOf(Known::class, $install->settings[0]->value->scalarInformation()->type);
    }
}

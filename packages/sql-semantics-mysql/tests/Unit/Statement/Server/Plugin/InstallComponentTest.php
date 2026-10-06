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

    public function testDeriveStatementDerivesTheVariablesAndValues(): void
    {
        $install = (new Semantics(Dialect::MySql))->analyze("INSTALL COMPONENT 'a' SET PERSIST a.v = 1");
        self::assertInstanceOf(InstallComponent::class, $install->statement);

        self::assertInstanceOf(Dependent::class, $install->facts->scalar($install->statement->settings[0]->variable)->type);
        self::assertInstanceOf(Known::class, $install->facts->scalar($install->statement->settings[0]->value)->type);
    }
}

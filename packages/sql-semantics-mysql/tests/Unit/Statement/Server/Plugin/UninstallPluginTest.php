<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin;

#[CoversClass(UninstallPlugin::class)]
#[Medium]
final class UninstallPluginTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('UNINSTALL PLUGIN p', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('uninstall plugin p')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('UNINSTALL PLUGIN p');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

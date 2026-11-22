<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UninstallPlugin::class)]
#[Medium]
final class UninstallPluginTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('UNINSTALL PLUGIN p', (new Inspection('mysql-5.7.44'))->inspect('uninstall plugin p')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('UNINSTALL PLUGIN p');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

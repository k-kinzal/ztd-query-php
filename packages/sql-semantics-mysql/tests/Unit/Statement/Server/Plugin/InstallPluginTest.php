<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InstallPlugin::class)]
#[Medium]
final class InstallPluginTest extends TestCase
{
    public function testRenderWritesNameAndLibrary(): void
    {
        self::assertSame("INSTALL PLUGIN p SONAME 'p.so'", (new Inspection('mysql-5.6.51'))->inspect("install plugin p soname 'p.so'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect("INSTALL PLUGIN p SONAME 'p.so'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

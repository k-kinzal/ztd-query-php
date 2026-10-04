<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin;

#[CoversClass(InstallPlugin::class)]
#[Medium]
final class InstallPluginTest extends TestCase
{
    public function testRenderWritesNameAndLibrary(): void
    {
        self::assertSame("INSTALL PLUGIN p SONAME 'p.so'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("install plugin p soname 'p.so'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("INSTALL PLUGIN p SONAME 'p.so'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent;

#[CoversClass(UninstallComponent::class)]
#[Medium]
final class UninstallComponentTest extends TestCase
{
    public function testRenderWritesTheComponents(): void
    {
        self::assertSame("UNINSTALL COMPONENT 'a'", (new Semantics(Dialect::MySql))->analyze("uninstall component 'a'")->toString());
    }

    public function testRenderRejectsARadixComponentName(): void
    {
        $this->expectExceptionMessage('A component URN is a quoted string.');
        new UninstallComponent([new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', radix: \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal)]);
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("UNINSTALL COMPONENT 'a', 'b'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

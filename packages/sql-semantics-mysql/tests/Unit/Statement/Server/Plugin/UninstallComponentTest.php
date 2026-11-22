<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UninstallComponent::class)]
#[Medium]
final class UninstallComponentTest extends TestCase
{
    public function testRenderWritesTheComponents(): void
    {
        self::assertSame("UNINSTALL COMPONENT 'a'", (new Inspection())->inspect("uninstall component 'a'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect("UNINSTALL COMPONENT 'a', 'b'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}

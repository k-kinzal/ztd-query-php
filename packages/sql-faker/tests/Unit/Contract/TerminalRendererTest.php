<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TerminalRenderer;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TokenSequence;

#[CoversNothing]
final class TerminalRendererTest extends TestCase
{
    public function testTerminalRendererContractCanBeImplemented(): void
    {
        $renderer = new class () implements TerminalRenderer {
            public function render(TerminalSequence $terminals): TokenSequence
            {
                return new TokenSequence($terminals->terminals);
            }
        };

        self::assertSame(['SELECT'], $renderer->render(new TerminalSequence(['SELECT']))->tokens);
    }
}

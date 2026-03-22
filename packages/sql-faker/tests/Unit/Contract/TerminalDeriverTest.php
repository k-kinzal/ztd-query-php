<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\TerminalDeriver;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;

#[CoversNothing]
final class TerminalDeriverTest extends TestCase
{
    public function testTerminalDeriverContractCanBeImplemented(): void
    {
        $deriver = new class () implements TerminalDeriver {
            public function derive(Grammar $grammar, TerminationLengths $terminationLengths, GenerationRequest $request): TerminalSequence
            {
                return new TerminalSequence([$request->startRule ?? $grammar->startSymbol]);
            }
        };

        self::assertSame(
            ['stmt'],
            $deriver->derive(new Grammar('stmt', []), new TerminationLengths([]), new GenerationRequest())->terminals,
        );
    }
}

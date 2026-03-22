<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\TerminationLengthComputer;
use SqlFaker\Contract\TerminationLengths;

#[CoversNothing]
final class TerminationLengthComputerTest extends TestCase
{
    public function testTerminationLengthComputerContractCanBeImplemented(): void
    {
        $computer = new class () implements TerminationLengthComputer {
            public function compute(Grammar $grammar): TerminationLengths
            {
                return new TerminationLengths([$grammar->startSymbol => 1]);
            }
        };

        self::assertSame(1, $computer->compute(new Grammar('stmt', []))->lengthOf('stmt'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Generation\TerminationLengthComputer;

#[CoversNothing]
final class TerminationLengthComputerTest extends TestCase
{
    public function testComputeReturnsMinimumTerminationLengths(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('expr', true)]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Symbol('IDENT', false)]),
            ]),
        ]);

        $lengths = (new TerminationLengthComputer())->compute($grammar);

        self::assertSame(1, $lengths->lengthOf('expr'));
        self::assertSame(1, $lengths->lengthOf('stmt'));
    }
}

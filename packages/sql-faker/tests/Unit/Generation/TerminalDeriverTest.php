<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Generation\TerminalDeriver;

#[CoversNothing]
final class TerminalDeriverTest extends TestCase
{
    public function testDeriveUsesDefaultStartRuleAndReturnsTerminalSequence(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('IDENT', false)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(Factory::create(), 'stmt');

        self::assertSame(
            ['IDENT'],
            $deriver->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest())->terminals,
        );
    }

    public function testSqliteModeTreatsUnknownNonTerminalsAsLiterals(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('MISSING', true)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(Factory::create(), 'stmt', true);

        self::assertSame(
            ['MISSING'],
            $deriver->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest())->terminals,
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\Compilation\PreparedGrammar;
use SqlFaker\Generation\Plan\Compilation\ScopedGeneration;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(PreparedGrammar::class)]
#[UsesClass(ScopedGeneration::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class PreparedGrammarTest extends TestCase
{
    public function testRestoreKeepsOriginalOrdinalsAndAncestorIdentity(): void
    {
        $grammar = new Grammar('@plan0', ['@plan0' => new ProductionRule('@plan0', [new Production([new Terminal('ID')], 7)])], ['@plan0' => 'name']);
        $sequence = new TerminalSequence([new TerminalOccurrence('ID', 1, [0], ['@plan0'])], productions: [new ProductionOccurrence(0, null, '@plan0', 0)]);
        $restored = (new PreparedGrammar($grammar, []))->restore($sequence)->sequence;
        self::assertSame('name', $restored->productions[0]->rule);
        self::assertSame(7, $restored->productions[0]->ordinal);
        self::assertSame(['name'], $restored->terminals[0]->rules);
        self::assertSame([0], $restored->terminals[0]->ancestors);
        self::assertSame($restored->terminals, $restored->original);
    }
}

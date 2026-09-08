<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\UniqueOptionRule;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;

#[CoversClass(UniqueOptionRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class UniqueOptionRuleTest extends TestCase
{
    public function testRewriteRetainsFirstValuesAndDoesNotShareStateBetweenScopes(): void
    {
        $first = new TerminalOccurrence('NOT', 2, [0, 1], ['column', 'option']);
        $duplicate = new TerminalOccurrence('NULL', 4, [0, 3], ['column', 'option']);
        $other = new TerminalOccurrence('NULL', 7, [5, 6], ['column', 'option']);
        $input = new TerminalSequence([$first, $duplicate, $other], [$first, $duplicate, $other], [], [
            new ProductionOccurrence(1, 0, 'option', 0), new ProductionOccurrence(3, 0, 'option', 1),
            new ProductionOccurrence(6, 5, 'option', 1),
        ]);
        $result = (new UniqueOptionRule('column', 'option', ['NOT' => 'null', 'NULL' => 'null'], null, 'source:option'))->rewrite($input);
        self::assertSame([$first, $other], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
    }

    public function testRewriteRemovesTheDuplicateSeparatorAndRetainsOtherCategories(): void
    {
        $trace = new DerivationTrace('trigger');
        $trace->expand(0, new Production([new NonTerminal('event'), new Terminal('OR'), new NonTerminal('event'), new Terminal('OR'), new NonTerminal('event')]), 0);
        $trace->expand(0, new Production([new Terminal('INSERT')]), 0);
        $trace->expand(2, new Production([new Terminal('INSERT')]), 0);
        $trace->expand(4, new Production([new Terminal('UPDATE')]), 1);
        $result = (new UniqueOptionRule('trigger', 'event', ['INSERT' => 'insert', 'UPDATE' => 'update'], 'OR', 'source:event'))->rewrite($trace->terminals());
        self::assertSame(['INSERT', 'OR', 'UPDATE'], $result->names());
    }
}

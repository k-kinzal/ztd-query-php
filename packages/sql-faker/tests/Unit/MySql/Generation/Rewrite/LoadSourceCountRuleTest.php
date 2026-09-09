<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\LoadSourceCountRule;

#[CoversClass(LoadSourceCountRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class LoadSourceCountRuleTest extends TestCase
{
    public function testRewriteConstrainsOnlyTheCountClause(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new Terminal('NUM'), new NonTerminal('opt_source_count')]), 0);
        $trace->expand(1, new Production([new NonTerminal('IDENT_sys'), new Terminal('NUM')]), 0);
        $trace->expand(1, new Production([new Terminal('IDENT')]), 0);
        $input = $trace->terminals();
        $result = (new LoadSourceCountRule())->rewrite($input);
        self::assertSame(['NUM', 'LOAD_COUNT_NAME', 'LOAD_SOURCE_COUNT'], $result->names());
        self::assertSame($input->terminals[1]->id, $result->terminals[1]->id);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }
}

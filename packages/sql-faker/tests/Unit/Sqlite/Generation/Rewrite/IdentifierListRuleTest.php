<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\IdentifierListRule;

#[CoversClass(IdentifierListRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class IdentifierListRuleTest extends TestCase
{
    public function testRewriteRemovesLegacyIdentifierSuffixesAndKeepsOrderBy(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('eidlist'), new NonTerminal('orderby')]), 0);
        $trace->expand(0, new Production([new Terminal('ID'), new NonTerminal('collate'), new NonTerminal('sortorder')]), 0);
        $trace->expand(1, new Production([new Terminal('COLLATE'), new Terminal('ID')]), 1);
        $trace->expand(3, new Production([new Terminal('DESC')]), 1);
        $trace->expand(4, new Production([new Terminal('ID'), new NonTerminal('sortorder')]), 0);
        $trace->expand(5, new Production([new Terminal('DESC')]), 1);
        $input = $trace->terminals();
        $result = (new IdentifierListRule())->rewrite($input);
        self::assertSame(['ID', 'ID', 'DESC'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->terminals[5], $result->terminals[2]);
    }
}

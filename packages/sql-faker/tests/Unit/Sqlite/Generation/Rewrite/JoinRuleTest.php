<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\JoinRule;

#[CoversClass(JoinRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class JoinRuleTest extends TestCase
{
    public function testRewriteMarksOnlyDirectModifierNames(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('joinop'), new NonTerminal('nm')]), 0);
        $trace->expand(0, new Production([new Terminal('JOIN_KW'), new NonTerminal('nm'), new Terminal('JOIN')]), 2);
        $trace->expand(1, new Production([new Terminal('ID')]), 0);
        $trace->expand(3, new Production([new Terminal('ID')]), 0);
        $input = $trace->terminals();
        $result = (new JoinRule())->rewrite($input);
        self::assertSame(['JOIN_KW', 'JOIN_MODIFIER', 'JOIN', 'ID'], $result->names());
        self::assertSame($input->terminals[3], $result->terminals[3]);
    }

    public function testRewriteRemovesConditionsOnlyWhenTheCorrespondingPrefixIsEmpty(): void
    {
        $trace = new DerivationTrace('seltablist');
        $trace->expand(0, new Production([new NonTerminal('stl_prefix'), new Terminal('ID'), new NonTerminal('on_using')]), 0);
        $trace->expand(0, new Production([]), 0);
        $trace->expand(1, new Production([new Terminal('ON'), new Terminal('INTEGER')]), 1);
        $input = $trace->terminals();
        $result = (new JoinRule())->rewrite($input);
        self::assertSame(['ID'], $result->names());
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewriteKeepsConditionsOfAJoinedTable(): void
    {
        $trace = new DerivationTrace('seltablist');
        $trace->expand(0, new Production([new NonTerminal('stl_prefix'), new Terminal('ID'), new NonTerminal('on_using')]), 0);
        $trace->expand(0, new Production([new Terminal('ID'), new Terminal('JOIN')]), 1);
        $trace->expand(3, new Production([new Terminal('ON'), new Terminal('INTEGER')]), 1);
        $input = $trace->terminals();
        self::assertSame($input, (new JoinRule())->rewrite($input));
    }
}

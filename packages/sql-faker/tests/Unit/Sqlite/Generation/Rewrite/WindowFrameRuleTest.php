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
use SqlFaker\Sqlite\Generation\Rewrite\WindowFrameRule;

#[CoversClass(WindowFrameRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class WindowFrameRuleTest extends TestCase
{
    public function testRewriteCompletesAShortFollowingFrameAndKeepsTheChosenOffset(): void
    {
        $trace = new DerivationTrace('frame_opt');
        $trace->expand(0, new Production([new NonTerminal('frame_bound_s')]), 0);
        $trace->expand(0, new Production([new Terminal('VALUE'), new Terminal('FOLLOWING')]), 1);
        $input = $trace->terminals();
        $result = (new WindowFrameRule())->rewrite($input);
        self::assertSame(['BETWEEN', 'VALUE', 'FOLLOWING', 'AND', 'UNBOUNDED', 'FOLLOWING'], $result->names());
        self::assertSame($input->terminals[0], $result->terminals[1]);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
    }

    public function testRewriteReplacesAnEarlierEndAndRetainsAValidFrame(): void
    {
        $trace = new DerivationTrace('frame_opt');
        $trace->expand(0, new Production([new Terminal('BETWEEN'), new NonTerminal('frame_bound_s'), new Terminal('AND'), new NonTerminal('frame_bound_e')]), 1);
        $trace->expand(1, new Production([new Terminal('CURRENT'), new Terminal('ROW')]), 2);
        $trace->expand(4, new Production([new Terminal('VALUE'), new Terminal('PRECEDING')]), 3);
        $result = (new WindowFrameRule())->rewrite($trace->terminals());
        self::assertSame(['BETWEEN', 'CURRENT', 'ROW', 'AND', 'UNBOUNDED', 'FOLLOWING'], $result->names());
        self::assertSame($result, (new WindowFrameRule())->rewrite($result));
    }

    public function testRankClassifiesEndpointsWithoutTreatingOffsetExpressionsAsSeparateBounds(): void
    {
        $trace = new DerivationTrace('frame_bound_s');
        $trace->expand(0, new Production([new Terminal('UNBOUNDED'), new Terminal('PRECEDING')]), 0);
        $rule = new WindowFrameRule();
        self::assertSame(0, $rule->rank($trace->terminals(), 0));
        self::assertSame(2, $rule->rank($trace->terminals(), 999));
    }
}

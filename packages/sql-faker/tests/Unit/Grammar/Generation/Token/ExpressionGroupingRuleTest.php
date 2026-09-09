<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;

#[CoversClass(ExpressionGroupingRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class ExpressionGroupingRuleTest extends TestCase
{
    public function testRewritePreservesBothNestedOperandsOfANonAssociativeComparison(): void
    {
        $trace = new DerivationTrace('a_expr');
        $trace->expand(0, new Production([new NonTerminal('a_expr'), new Terminal('<'), new NonTerminal('a_expr')]), 0);
        $trace->expand(0, new Production([new Terminal('1'), new Terminal('='), new Terminal('2')]), 1);
        $trace->expand(4, new Production([new Terminal('3'), new Terminal('>'), new Terminal('4')]), 2);
        $input = $trace->terminals();
        $rule = new ExpressionGroupingRule(['a_expr', 'b_expr'], 'source');
        $result = $rule->rewrite($input);
        self::assertSame(['(', '1', '=', '2', ')', '<', '(', '3', '>', '4', ')'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result->terminals, $rule->rewrite($result)->terminals);
        self::assertSame([0, 1], $result->terminals[0]->ancestors);
    }

    public function testRewriteGroupsARestrictedOperandInsideTheGeneralExpressionFamily(): void
    {
        $trace = new DerivationTrace('a_expr');
        $trace->expand(0, new Production([new Terminal('1'), new Terminal('BETWEEN'), new NonTerminal('b_expr'), new Terminal('AND'), new NonTerminal('a_expr')]), 0);
        $trace->expand(2, new Production([new Terminal('2'), new Terminal('+'), new Terminal('3')]), 1);
        $trace->expand(6, new Production([new Terminal('4')]), 0);
        self::assertSame(['1', 'BETWEEN', '(', '2', '+', '3', ')', 'AND', '4'], (new ExpressionGroupingRule(['a_expr', 'b_expr'], 'source'))->rewrite($trace->terminals())->names());
    }

    public function testRewritePreservesTopLevelExpressionsAndUnrelatedNestedRules(): void
    {
        $trace = new DerivationTrace('stmt');
        $trace->expand(0, new Production([new Terminal('SELECT'), new NonTerminal('a_expr')]), 0);
        $trace->expand(1, new Production([new NonTerminal('function'), new Terminal('+'), new NonTerminal('a_expr')]), 0);
        $trace->expand(1, new Production([new Terminal('F'), new Terminal('('), new Terminal(')')]), 0);
        $trace->expand(5, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new ExpressionGroupingRule(['a_expr'], 'source'))->rewrite($input));
    }
    public function testRangesIncludesNestedAncestorsAndIgnoresEmptyOccurrences(): void
    {
        $a = new TerminalOccurrence('1', 2, [0, 1], ['a_expr', 'a_expr']);
        $b = new TerminalOccurrence('+', 3, [0], ['a_expr']);
        $rule = new ExpressionGroupingRule(['a_expr'], 'source');
        self::assertSame([0 => [0, 2], 1 => [0, 1]], $rule->ranges(new TerminalSequence([$a, $b])));
        self::assertSame([], $rule->ranges(new TerminalSequence([])));
        $empty = new TerminalSequence([]);
        self::assertSame($empty, $rule->rewrite($empty));
    }
    public function testRewriteUsesTheConfiguredDialectTerminalNames(): void
    {
        $trace = new DerivationTrace('expr');
        $trace->expand(0, new Production([new NonTerminal('expr'), new Terminal('EQ'), new Terminal('INTEGER')]), 0);
        $trace->expand(0, new Production([new Terminal('INTEGER'), new Terminal('PLUS'), new Terminal('INTEGER')]), 1);
        $rule = new ExpressionGroupingRule(['expr'], 'parse.y', 'LP', 'RP');
        self::assertSame(['LP', 'INTEGER', 'PLUS', 'INTEGER', 'RP', 'EQ', 'INTEGER'], $rule->rewrite($trace->terminals())->names());
    }
}

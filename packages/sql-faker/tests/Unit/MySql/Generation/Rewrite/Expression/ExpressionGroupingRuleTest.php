<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\Expression\ExpressionGroupingRule;

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

    public function testRewriteRetainsNestedBoundaryProvenanceAndPreviouslyInsertedIdentities(): void
    {
        $a = new TerminalOccurrence('NOT', -1, [0, 1, 2], ['a_expr', 'b_expr', 'a_expr'], 'earlier');
        $b = new TerminalOccurrence('TRUE', 10, [0, 1, 2], ['a_expr', 'b_expr', 'a_expr']);
        $tail = new TerminalOccurrence('TAIL', 11, [0], ['a_expr']);
        $input = new TerminalSequence([$a, $b, $tail], [$a, $b, $tail], ['earlier'], [
            new ProductionOccurrence(0, null, 'a_expr', 0),
            new ProductionOccurrence(1, 0, 'b_expr', 0),
            new ProductionOccurrence(2, 1, 'a_expr', 0),
        ]);
        $result = (new ExpressionGroupingRule(['a_expr', 'b_expr'], 'group'))->rewrite($input);
        self::assertSame(['(', '(', 'NOT', 'TRUE', ')', ')', 'TAIL'], $result->names());
        $ids = array_map(static fn (TerminalOccurrence $terminal): int => $terminal->id, $result->terminals);
        self::assertCount(count($ids), array_unique($ids));
        self::assertLessThan(-1, max($ids[0], $ids[1], $ids[4], $ids[5]));
        self::assertSame([0, 1], $result->terminals[0]->ancestors);
        self::assertSame(['a_expr', 'b_expr'], $result->terminals[0]->rules);
        self::assertSame([0, 1, 2], $result->terminals[4]->ancestors);
        self::assertSame(['a_expr', 'b_expr', 'a_expr'], $result->terminals[4]->rules);
        self::assertSame([0, 1], $result->terminals[5]->ancestors);
        self::assertSame($a, $result->terminals[2]);
        self::assertSame($b, $result->terminals[3]);
        self::assertSame(['earlier', 'group'], $result->rewrites);
    }

    public function testRewriteContinuesAfterAnAlreadyGroupedOperand(): void
    {
        $left = new TerminalOccurrence('(', -1, [0, 1], ['expr', 'expr'], 'group');
        $leftEnd = new TerminalOccurrence(')', -2, [0, 1], ['expr', 'expr'], 'group');
        $right = new TerminalOccurrence('NOT', 10, [0, 2], ['expr', 'expr']);
        $rightEnd = new TerminalOccurrence('TRUE', 11, [0, 2], ['expr', 'expr']);
        $input = new TerminalSequence([$left, $leftEnd, $right, $rightEnd], [], [], [
            new ProductionOccurrence(0, null, 'expr', 0),
            new ProductionOccurrence(1, 0, 'expr', 0),
            new ProductionOccurrence(2, 0, 'expr', 0),
        ]);
        $result = (new ExpressionGroupingRule(['expr'], 'group'))->rewrite($input);
        self::assertSame(['(', ')', '(', 'NOT', 'TRUE', ')'], $result->names());
        self::assertSame($left, $result->terminals[0]);
        self::assertSame($leftEnd, $result->terminals[1]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Rewrite\Query\WindowFrameRule;

#[CoversClass(WindowFrameRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class WindowFrameRuleTest extends TestCase
{
    #[DataProvider('providerUnits')]
    public function testRewriteChangesOnlyRowsFramesWithAnIntervalBoundary(string $units, string $bound, string $expected): void
    {
        $trace = new DerivationTrace('opt_window_frame_clause');
        $trace->expand(0, new Production([new NonTerminal('window_frame_units'), new NonTerminal('window_frame_extent')]), 0);
        $trace->expand(0, new Production([new Terminal($units)]), 0);
        $trace->expand(1, new Production([new NonTerminal('window_frame_start')]), 0);
        $trace->expand(1, new Production([new Terminal($bound), new Terminal('VALUE'), new Terminal('PRECEDING_SYM')]), 0);
        $input = $trace->terminals();
        $rule = new WindowFrameRule();
        $result = $rule->rewrite($input);
        self::assertSame([$expected, $bound, 'VALUE', 'PRECEDING_SYM'], $result->names());
        self::assertSame($input->terminals[1], $result->terminals[1]);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerUnits(): iterable
    {
        yield 'rows interval' => ['ROWS_SYM', 'INTERVAL_SYM', 'RANGE_SYM'];
        yield 'range interval' => ['RANGE_SYM', 'INTERVAL_SYM', 'RANGE_SYM'];
        yield 'groups interval' => ['GROUPS_SYM', 'INTERVAL_SYM', 'GROUPS_SYM'];
        yield 'rows number' => ['ROWS_SYM', 'NUM', 'ROWS_SYM'];
    }

    public function testRewriteDoesNotUseIntervalsFromNestedFramesOrOffsetExpressions(): void
    {
        $trace = new DerivationTrace('opt_window_frame_clause');
        $trace->expand(0, new Production([new NonTerminal('window_frame_units'), new NonTerminal('expr'), new NonTerminal('opt_window_frame_clause')]), 0);
        $trace->expand(0, new Production([new Terminal('ROWS_SYM')]), 0);
        $trace->expand(1, new Production([new Terminal('INTERVAL_SYM')]), 0);
        $trace->expand(2, new Production([new NonTerminal('window_frame_units'), new NonTerminal('window_frame_bound')]), 0);
        $trace->expand(2, new Production([new Terminal('ROWS_SYM')]), 0);
        $trace->expand(3, new Production([new Terminal('INTERVAL_SYM'), new Terminal('VALUE'), new Terminal('FOLLOWING_SYM')]), 0);
        $input = $trace->terminals();
        $rule = new WindowFrameRule();
        $result = $rule->rewrite($input);
        self::assertSame(['ROWS_SYM', 'INTERVAL_SYM', 'RANGE_SYM', 'INTERVAL_SYM', 'VALUE', 'FOLLOWING_SYM'], $result->names());
        self::assertSame($input->terminals[0], $result->terminals[0]);
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testRewriteKeepsAnAbsentFrameAndUnboundedKeepsAnAbsentBoundary(): void
    {
        $trace = new DerivationTrace('opt_window_frame_clause');
        $trace->expand(0, new Production([]), 0);
        $input = $trace->terminals();
        $rule = new WindowFrameRule();
        self::assertSame($input, $rule->rewrite($input));
        self::assertSame($input, $rule->unbounded($input, 999, 'FOLLOWING_SYM'));
    }

    public function testRewriteReplacesAnEarlierEndAndRetainsAValidFrame(): void
    {
        $trace = new DerivationTrace('window_frame_between');
        $trace->expand(0, new Production([new Terminal('BETWEEN_SYM'), new NonTerminal('window_frame_bound'), new Terminal('AND_SYM'), new NonTerminal('window_frame_bound')]), 1);
        $trace->expand(1, new Production([new Terminal('CURRENT_SYM'), new Terminal('ROW_SYM')]), 2);
        $trace->expand(4, new Production([new Terminal('VALUE'), new Terminal('PRECEDING_SYM')]), 3);
        $result = (new WindowFrameRule())->rewrite($trace->terminals());
        self::assertSame(['BETWEEN_SYM', 'CURRENT_SYM', 'ROW_SYM', 'AND_SYM', 'UNBOUNDED_SYM', 'FOLLOWING_SYM'], $result->names());
        self::assertSame($result, (new WindowFrameRule())->rewrite($result));
    }

    public function testRankClassifiesEndpointsWithoutTreatingOffsetExpressionsAsSeparateBounds(): void
    {
        $trace = new DerivationTrace('window_frame_bound');
        $trace->expand(0, new Production([new Terminal('UNBOUNDED_SYM'), new Terminal('PRECEDING_SYM')]), 0);
        $rule = new WindowFrameRule();
        self::assertSame(0, $rule->rank($trace->terminals(), 0));
        self::assertSame(2, $rule->rank($trace->terminals(), 999));
    }

    public function testUnboundedReplacesTheEntireEndpointAndKeepsItsScope(): void
    {
        $trace = new DerivationTrace('window_frame_bound');
        $trace->expand(0, new Production([new Terminal('CURRENT_P'), new Terminal('ROW_SYM')]), 2);
        $result = (new WindowFrameRule())->unbounded($trace->terminals(), 0, 'FOLLOWING_SYM');
        self::assertSame(['UNBOUNDED_SYM', 'FOLLOWING_SYM'], $result->names());
        self::assertSame([0], $result->terminals[0]->ancestors);
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('providerBoundaries')]
    public function testRankRetainsTheFiveSourceBoundaryCategories(array $names, int $expected): void
    {
        $trace = new DerivationTrace('window_frame_bound');
        $trace->expand(0, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $names)), 0);
        self::assertSame($expected, (new WindowFrameRule())->rank($trace->terminals(), 0));
    }

    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function providerBoundaries(): iterable
    {
        yield 'unbounded preceding' => [['UNBOUNDED_SYM', 'PRECEDING_SYM'], 0];
        yield 'offset preceding' => [['VALUE', 'PRECEDING_SYM'], 1];
        yield 'current row' => [['CURRENT_SYM', 'ROW_SYM'], 2];
        yield 'offset following' => [['VALUE', 'FOLLOWING_SYM'], 3];
        yield 'unbounded following' => [['UNBOUNDED_SYM', 'FOLLOWING_SYM'], 4];
    }

    /**
     * @param list<string> $start
     * @param list<string> $end
     * @param list<string> $expected
     */
    #[DataProvider('providerFrames')]
    public function testRewritePreservesValidBoundariesAndRepairsInvalidOrdering(array $start, array $end, array $expected): void
    {
        $trace = new DerivationTrace('window_frame_between');
        $trace->expand(0, new Production([new Terminal('BETWEEN_SYM'), new NonTerminal('window_frame_bound'), new Terminal('AND_SYM'), new NonTerminal('window_frame_bound')]), 0);
        $trace->expand(1, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $start)), 0);
        $trace->expand(2 + count($start), new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $end)), 0);
        $input = $trace->terminals();
        $rule = new WindowFrameRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>, list<string>}>
     */
    public static function providerFrames(): iterable
    {
        yield 'equal offsets' => [['VALUE', 'FOLLOWING_SYM'], ['VALUE', 'FOLLOWING_SYM'], ['BETWEEN_SYM', 'VALUE', 'FOLLOWING_SYM', 'AND_SYM', 'VALUE', 'FOLLOWING_SYM']];
        yield 'equal current' => [['CURRENT_SYM', 'ROW_SYM'], ['CURRENT_SYM', 'ROW_SYM'], ['BETWEEN_SYM', 'CURRENT_SYM', 'ROW_SYM', 'AND_SYM', 'CURRENT_SYM', 'ROW_SYM']];
        yield 'preceding through current' => [['VALUE', 'PRECEDING_SYM'], ['CURRENT_SYM', 'ROW_SYM'], ['BETWEEN_SYM', 'VALUE', 'PRECEDING_SYM', 'AND_SYM', 'CURRENT_SYM', 'ROW_SYM']];
        yield 'current through following' => [['CURRENT_SYM', 'ROW_SYM'], ['VALUE', 'FOLLOWING_SYM'], ['BETWEEN_SYM', 'CURRENT_SYM', 'ROW_SYM', 'AND_SYM', 'VALUE', 'FOLLOWING_SYM']];
        yield 'full range' => [['UNBOUNDED_SYM', 'PRECEDING_SYM'], ['UNBOUNDED_SYM', 'FOLLOWING_SYM'], ['BETWEEN_SYM', 'UNBOUNDED_SYM', 'PRECEDING_SYM', 'AND_SYM', 'UNBOUNDED_SYM', 'FOLLOWING_SYM']];
        yield 'following through current' => [['VALUE', 'FOLLOWING_SYM'], ['CURRENT_SYM', 'ROW_SYM'], ['BETWEEN_SYM', 'VALUE', 'FOLLOWING_SYM', 'AND_SYM', 'UNBOUNDED_SYM', 'FOLLOWING_SYM']];
        yield 'following through preceding' => [['VALUE', 'FOLLOWING_SYM'], ['VALUE', 'PRECEDING_SYM'], ['BETWEEN_SYM', 'VALUE', 'FOLLOWING_SYM', 'AND_SYM', 'UNBOUNDED_SYM', 'FOLLOWING_SYM']];
        yield 'forbidden start' => [['UNBOUNDED_SYM', 'FOLLOWING_SYM'], ['CURRENT_SYM', 'ROW_SYM'], ['BETWEEN_SYM', 'UNBOUNDED_SYM', 'PRECEDING_SYM', 'AND_SYM', 'CURRENT_SYM', 'ROW_SYM']];
        yield 'forbidden end' => [['UNBOUNDED_SYM', 'PRECEDING_SYM'], ['UNBOUNDED_SYM', 'PRECEDING_SYM'], ['BETWEEN_SYM', 'UNBOUNDED_SYM', 'PRECEDING_SYM', 'AND_SYM', 'UNBOUNDED_SYM', 'FOLLOWING_SYM']];
    }

    public function testRewriteLeavesAnEmptyFrameUnchanged(): void
    {
        $trace = new DerivationTrace('window_frame_between');
        $trace->expand(0, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new WindowFrameRule())->rewrite($input));
    }
}

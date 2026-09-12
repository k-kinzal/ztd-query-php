<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\WindowFrameRule;

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
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, (new WindowFrameRule())->rewrite($result));
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

    /**
     * @param list<string> $names
     */
    #[DataProvider('providerBoundaries')]
    public function testRankRetainsTheFiveSourceBoundaryCategories(array $names, int $expected): void
    {
        $trace = new DerivationTrace('frame_bound_s');
        $trace->expand(0, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $names)), 0);
        self::assertSame($expected, (new WindowFrameRule())->rank($trace->terminals(), 0));
    }

    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function providerBoundaries(): iterable
    {
        yield 'unbounded preceding' => [['UNBOUNDED', 'PRECEDING'], 0];
        yield 'offset preceding' => [['VALUE', 'PRECEDING'], 1];
        yield 'current row' => [['CURRENT', 'ROW'], 2];
        yield 'offset following' => [['VALUE', 'FOLLOWING'], 3];
        yield 'unbounded following' => [['UNBOUNDED', 'FOLLOWING'], 4];
    }

    /**
     * @param list<string> $start
     * @param list<string> $end
     * @param list<string> $expected
     */
    #[DataProvider('providerFrames')]
    public function testRewritePreservesValidBoundariesAndRepairsInvalidOrdering(array $start, array $end, array $expected): void
    {
        $trace = new DerivationTrace('frame_opt');
        $trace->expand(0, new Production([new Terminal('BETWEEN'), new NonTerminal('frame_bound_s'), new Terminal('AND'), new NonTerminal('frame_bound_e')]), 0);
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
        yield 'equal offsets' => [['VALUE', 'FOLLOWING'], ['VALUE', 'FOLLOWING'], ['BETWEEN', 'VALUE', 'FOLLOWING', 'AND', 'VALUE', 'FOLLOWING']];
        yield 'equal current' => [['CURRENT', 'ROW'], ['CURRENT', 'ROW'], ['BETWEEN', 'CURRENT', 'ROW', 'AND', 'CURRENT', 'ROW']];
        yield 'preceding through current' => [['VALUE', 'PRECEDING'], ['CURRENT', 'ROW'], ['BETWEEN', 'VALUE', 'PRECEDING', 'AND', 'CURRENT', 'ROW']];
        yield 'current through following' => [['CURRENT', 'ROW'], ['VALUE', 'FOLLOWING'], ['BETWEEN', 'CURRENT', 'ROW', 'AND', 'VALUE', 'FOLLOWING']];
        yield 'full range' => [['UNBOUNDED', 'PRECEDING'], ['UNBOUNDED', 'FOLLOWING'], ['BETWEEN', 'UNBOUNDED', 'PRECEDING', 'AND', 'UNBOUNDED', 'FOLLOWING']];
        yield 'following through current' => [['VALUE', 'FOLLOWING'], ['CURRENT', 'ROW'], ['BETWEEN', 'VALUE', 'FOLLOWING', 'AND', 'UNBOUNDED', 'FOLLOWING']];
        yield 'following through preceding' => [['VALUE', 'FOLLOWING'], ['VALUE', 'PRECEDING'], ['BETWEEN', 'VALUE', 'FOLLOWING', 'AND', 'UNBOUNDED', 'FOLLOWING']];
    }

    public function testRewriteLeavesAnEmptyFrameUnchanged(): void
    {
        $trace = new DerivationTrace('frame_opt');
        $trace->expand(0, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new WindowFrameRule())->rewrite($input));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Query\JoinGroupingRule;

#[CoversClass(JoinGroupingRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class JoinGroupingRuleTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\TestWith(['joined_table'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['join_table'])]
    public function testRewriteKeepsNestedJoinConditionsWithTheirOwnOperands(string $production): void
    {
        $a = new TerminalOccurrence('A', 10, [0], [$production]);
        $outer = new TerminalOccurrence('LEFT', 11, [0], [$production]);
        $b = new TerminalOccurrence('B', 12, [0, 1], [$production, $production]);
        $inner = new TerminalOccurrence('JOIN_SYM', 13, [0, 1], [$production, $production]);
        $c = new TerminalOccurrence('C', 14, [0, 1], [$production, $production]);
        $using = new TerminalOccurrence('USING', 15, [0], [$production]);
        $tokens = [$a, $outer, $b, $inner, $c, $using];
        $input = new TerminalSequence($tokens, $tokens, [], [new ProductionOccurrence(0, null, $production, 3), new ProductionOccurrence(1, 0, $production, 4), new ProductionOccurrence(9, null, $production, 0)]);
        $rule = new JoinGroupingRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', 'A', 'LEFT', '(', 'B', 'JOIN_SYM', 'C', ')', 'USING', ')'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testRewriteGroupsAnOuterJoinEvenWhenItsLeftChildIsAlreadyGrouped(): void
    {
        $a = new TerminalOccurrence('A', 10, [0, 1], ['joined_table', 'joined_table']);
        $b = new TerminalOccurrence('B', 11, [0], ['joined_table']);
        $input = new TerminalSequence([$a, $b], [$a, $b], [], [new ProductionOccurrence(0, null, 'joined_table', 0), new ProductionOccurrence(1, 0, 'joined_table', 0)]);
        $rule = new JoinGroupingRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', '(', 'A', ')', 'B', ')'], $result->names());
        self::assertSame($result, $rule->rewrite($result));
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewritePreservesTokensWithoutJoinProductions(): void
    {
        $input = TerminalSequence::fromNames(['A', 'JOIN_SYM', 'B']);
        self::assertSame($input, (new JoinGroupingRule())->rewrite($input));
    }
}

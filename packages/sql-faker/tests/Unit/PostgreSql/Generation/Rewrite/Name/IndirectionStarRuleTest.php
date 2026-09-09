<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\IndirectionStarRule;

#[CoversClass(IndirectionStarRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class IndirectionStarRuleTest extends TestCase
{
    public function testRewriteRemovesOnlyNonFinalStarsWithinTheirOwnChain(): void
    {
        $dot = new TerminalOccurrence('.', 10, [0, 1, 2, 3], ['columnref', 'opt_indirection', 'opt_indirection', 'indirection_el']);
        $star = new TerminalOccurrence('*', 11, $dot->ancestors, $dot->rules);
        $nextDot = new TerminalOccurrence('.', 12, [0, 1, 4], ['columnref', 'opt_indirection', 'indirection_el']);
        $lastStar = new TerminalOccurrence('*', 13, $nextDot->ancestors, $nextDot->rules);
        $multiply = new TerminalOccurrence('*', 14, [5], ['a_expr']);
        $otherDot = new TerminalOccurrence('.', 15, [6, 7], ['indirection', 'indirection_el']);
        $otherStar = new TerminalOccurrence('*', 16, $otherDot->ancestors, $otherDot->rules);
        $input = new TerminalSequence([$dot, $star, $nextDot, $lastStar, $multiply, $otherDot, $otherStar], [], [], [new ProductionOccurrence(3, 2, 'indirection_el', 0), new ProductionOccurrence(4, 1, 'indirection_el', 0), new ProductionOccurrence(7, 6, 'indirection_el', 0)]);
        $rule = new IndirectionStarRule();
        $result = $rule->rewrite($input);
        self::assertSame([$nextDot, $lastStar, $multiply, $otherDot, $otherStar], $result->terminals);
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testRewriteKeepsNamedComponentsAndEmptyOccurrences(): void
    {
        $dot = new TerminalOccurrence('.', 2, [0, 1], ['indirection', 'indirection_el']);
        $name = new TerminalOccurrence('IDENT', 3, $dot->ancestors, $dot->rules);
        $input = new TerminalSequence([$dot, $name], [], [], [new ProductionOccurrence(1, 0, 'indirection_el', 0), new ProductionOccurrence(4, 0, 'indirection_el', 0)]);
        self::assertSame($input, (new IndirectionStarRule())->rewrite($input));
    }
}

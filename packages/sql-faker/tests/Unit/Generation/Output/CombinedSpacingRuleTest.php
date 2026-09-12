<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(CombinedSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
final class CombinedSpacingRuleTest extends TestCase
{
    public function testApplyPreservesContradictionsAcrossOrderAndDuplicateRules(): void
    {
        $join = $this->createMock(SpacingRule::class);
        $join->method('apply')->willReturn(new SpacingConstraint(SpacingConstraint::JOIN, ['join']));
        $space = $this->createMock(SpacingRule::class);
        $space->method('apply')->willReturn(new SpacingConstraint(SpacingConstraint::SPACE, ['space']));
        $none = $this->createMock(SpacingRule::class);
        $none->method('apply')->willReturn(null);
        $sequence = TerminalSequence::fromNames(['X', 'Y']);
        $boundary = new LexemeBoundary(new Lexeme('X', 'keyword', $sequence->terminals[0], 'x'), new Lexeme('Y', 'keyword', $sequence->terminals[1], 'y'));
        $input = new LexemeInput($sequence, 0, new ResolvedOutput());
        $forward = (new CombinedSpacingRule($join, $space))->apply($boundary, $input);
        $reverse = (new CombinedSpacingRule($space, $none, $join))->apply($boundary, $input);
        $duplicate = (new CombinedSpacingRule($join, $join, $space))->apply($boundary, $input);
        self::assertSame([0, 0, 0], [$forward->allowed, $reverse->allowed, $duplicate->allowed]);
        self::assertSame([null, null, null], [$forward->separator(), $reverse->separator(), $duplicate->separator()]);
        self::assertSame(['join', 'space'], $forward->rules);
        self::assertSame(['space', 'join'], $reverse->rules);
        self::assertSame($forward->rules, $duplicate->rules);
        self::assertSame(' ', (new CombinedSpacingRule($none))->apply($boundary, $input)->separator());
    }
}

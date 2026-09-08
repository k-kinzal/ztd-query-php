<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Spacing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(SpacingRule::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class SpacingRuleTest extends TestCase
{
    public function testApplyContractPermitsUnconstrainedBoundaries(): void
    {
        $rule = new CombinedSpacingRule();
        $sequence = TerminalSequence::fromNames(['A', 'B']);
        $boundary = new LexemeBoundary(new Lexeme('a', 'identifier', $sequence->terminals[0], 'source'), new Lexeme('b', 'identifier', $sequence->terminals[1], 'source'));
        self::assertSame(SpacingConstraint::EITHER, $rule->apply($boundary, new LexemeInput($sequence, 0, new ResolvedOutput()))->allowed);
    }
}

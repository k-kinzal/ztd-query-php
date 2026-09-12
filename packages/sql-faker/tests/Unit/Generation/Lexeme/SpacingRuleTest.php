<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Lexeme;

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

#[CoversClass(SpacingRule::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
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

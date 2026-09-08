<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Spacing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(KeywordPhraseSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(SpacingRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class KeywordPhraseSpacingRuleTest extends TestCase
{
    public function testApplySeparatesOnlyPartsOfTheSameCompoundOccurrence(): void
    {
        $sequence = TerminalSequence::fromNames(['WITH_ROLLUP', 'WITH_ROLLUP']);
        $input = new LexemeInput($sequence, 0, new ResolvedOutput());
        $left = new Lexeme('WITH', 'keyword', $sequence->terminals[0], 'source', 'phrase');
        $right = new Lexeme('ROLLUP', 'keyword', $sequence->terminals[0], 'source', 'phrase');
        $rule = new KeywordPhraseSpacingRule();
        self::assertSame(SpacingConstraint::SPACE, $rule->apply(new LexemeBoundary($left, $right), $input)?->allowed);
        $other = new Lexeme('ROLLUP', 'keyword', $sequence->terminals[1], 'source', 'phrase');
        self::assertNull($rule->apply(new LexemeBoundary($left, $other), $input));
    }
}

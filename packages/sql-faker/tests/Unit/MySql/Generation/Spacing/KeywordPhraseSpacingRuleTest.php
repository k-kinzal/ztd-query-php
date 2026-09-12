<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Spacing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule;

#[CoversClass(KeywordPhraseSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(SpacingRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
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

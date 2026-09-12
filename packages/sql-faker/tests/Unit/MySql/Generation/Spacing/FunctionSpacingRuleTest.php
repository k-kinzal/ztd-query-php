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
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule;

#[CoversClass(FunctionSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(SpacingRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
final class FunctionSpacingRuleTest extends TestCase
{
    public function testApplyConstrainsOnlyItsSourceDefinedBoundary(): void
    {
        $origin = new TerminalOccurrence('TOKEN', 1, [0], []);
        $input = new LexemeInput(new TerminalSequence([$origin]), 0, new ResolvedOutput());
        $left = new Lexeme('NOW', 'function', $origin, 'source');
        $right = new Lexeme('(', 'symbol', $origin, 'source');
        $rule = new FunctionSpacingRule();
        self::assertSame(SpacingConstraint::JOIN, $rule->apply(new LexemeBoundary($left, $right), $input)?->allowed);
        $plain = new Lexeme('word', 'keyword', new TerminalOccurrence('OTHER', 2), 'other');
        self::assertNull($rule->apply(new LexemeBoundary($plain, $plain), $input));
    }
}

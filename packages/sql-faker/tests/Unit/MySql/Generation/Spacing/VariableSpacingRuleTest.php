<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Spacing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Spacing\VariableSpacingRule;

#[CoversClass(VariableSpacingRule::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(SpacingRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class VariableSpacingRuleTest extends TestCase
{
    public function testApplyConstrainsOnlyItsSourceDefinedBoundary(): void
    {
        $origin = new TerminalOccurrence('TOKEN', 1, [0], []);
        $input = new LexemeInput(new TerminalSequence([$origin]), 0, new ResolvedOutput());
        $left = new Lexeme('@', 'symbol', $origin, 'source');
        $right = new Lexeme('name', 'symbol', $origin, 'source');
        $rule = new VariableSpacingRule();
        self::assertSame(SpacingConstraint::JOIN, $rule->apply(new LexemeBoundary($left, $right), $input)?->allowed);
        $plain = new Lexeme('word', 'keyword', new TerminalOccurrence('OTHER', 2), 'other');
        self::assertNull($rule->apply(new LexemeBoundary($plain, $plain), $input));
    }
}

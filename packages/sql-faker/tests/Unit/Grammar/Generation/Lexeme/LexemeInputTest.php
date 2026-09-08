<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class LexemeInputTest extends TestCase
{
    public function testTerminalUsesTheOccurrenceIndexInsteadOfMatchingRepeatedNames(): void
    {
        $sequence = TerminalSequence::fromNames(['IDENT', 'IDENT']);
        $input = new LexemeInput($sequence, 1, new ResolvedOutput(), 'chosen');
        self::assertSame($sequence->terminals[1], $input->terminal());
        self::assertSame('chosen', $input->requested);
    }
}

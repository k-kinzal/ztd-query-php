<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
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

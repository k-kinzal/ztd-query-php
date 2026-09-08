<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(FixedLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class FixedLexemeGeneratorTest extends TestCase
{
    public function testGeneratePreservesTheTerminalOccurrenceAndSourceForCompoundPhrases(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP']), 0, new ResolvedOutput());
        $candidate = [...(new FixedLexemeGenerator('WITH', 'keyword', 'lexer:phrase', 'rollup'))->generate($input)->sequences()][0];
        self::assertSame('WITH', $candidate->lexemes[0]->text);
        self::assertSame($input->terminal(), $candidate->lexemes[0]->origin);
        self::assertSame('rollup', $candidate->lexemes[0]->phrase);
        self::assertSame('lexer:phrase', $candidate->lexemes[0]->definition);
    }

    public function testGenerateRepresentsAnEmptyMarkerAsOneCandidateWithNoLexemes(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['EOF']), 0, new ResolvedOutput());
        $candidates = [...(new FixedLexemeGenerator('', 'marker', 'lexer:eof'))->generate($input)->sequences()];
        self::assertCount(1, $candidates);
        self::assertSame([], $candidates[0]->lexemes);
    }
}

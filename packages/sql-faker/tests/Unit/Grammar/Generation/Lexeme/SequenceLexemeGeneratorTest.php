<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(SequenceLexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class SequenceLexemeGeneratorTest extends TestCase
{
    public function testGenerateACompoundCandidateKeepsOutputOrderAndConsumesOneOccurrence(): void
    {
        $generator = new SequenceLexemeGenerator(
            new FixedLexemeGenerator('WITH', 'keyword', 'with'),
            new ChoiceLexemeGenerator(
                new FixedLexemeGenerator('ROLLUP', 'keyword', 'rollup'),
                new FixedLexemeGenerator('CUBE', 'keyword', 'cube'),
            ),
        );
        $input = new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP_SYM']), 0, new ResolvedOutput());
        $result = $generator->generate($input);
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertCount(2, $candidates);
        self::assertSame(['WITH', 'ROLLUP'], array_column($candidates[0]->lexemes, 'text'));
        self::assertSame(['WITH', 'CUBE'], array_column($candidates[1]->lexemes, 'text'));
        self::assertSame([[0, 0], [0, 0]], array_map(
            static fn ($candidate): array => array_map(static fn ($lexeme): int => $lexeme->origin->id, $candidate->lexemes),
            $candidates,
        ));
    }

    public function testARequiredNonApplicableChildCannotBeOmitted(): void
    {
        $generator = new SequenceLexemeGenerator(
            new FixedLexemeGenerator('WITH', 'keyword', 'with'),
            new MatchingLexemeGenerator('OTHER', new FixedLexemeGenerator('ROLLUP', 'keyword', 'rollup')),
        );
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP_SYM']), 0, new ResolvedOutput())));
    }

    public function testProductRetainsTheWholePrefixAndRejectsAnEmptyRequiredFactor(): void
    {
        $prefix = new LexemeSequence([], 'prefix');
        $generator = new SequenceLexemeGenerator();
        self::assertSame([$prefix], [...$generator->product([], 0, $prefix)]);
        self::assertSame([], [...$generator->product([LexemeCandidates::of()], 0, $prefix)]);
    }

}

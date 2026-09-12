<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Candidate\MatchingLexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(LexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
final class LexemeGeneratorTest extends TestCase
{
    public function testGenerateContractDistinguishesNonApplicabilityAndAnEmptyOutput(): void
    {
        $generator = new MatchingLexemeGenerator('END', new FixedLexemeGenerator('', 'marker', 'parser:END'));
        $input = new LexemeInput(TerminalSequence::fromNames(['NAME']), 0, new ResolvedOutput());
        self::assertNull($generator->generate($input));
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['END']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame([], [...$result->sequences()][0]->lexemes);
    }
}

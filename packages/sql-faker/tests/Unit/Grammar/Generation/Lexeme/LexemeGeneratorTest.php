<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(LexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
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

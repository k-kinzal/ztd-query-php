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
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(MatchingLexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(PatternLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class MatchingLexemeGeneratorTest extends TestCase
{
    public function testGenerateDistinguishesNonApplicabilityFromAMatchedEmptyCandidateSet(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['NAME']), 0, new ResolvedOutput());
        $child = new PatternLexemeGenerator('NAME', '/^x$/', ['y'], 'identifier', 'test');
        self::assertNull((new MatchingLexemeGenerator('OTHER', $child))->generate($input));
        $result = (new MatchingLexemeGenerator('NAME', $child))->generate($input);
        self::assertNotNull($result);
        self::assertSame([], [...$result->sequences()]);
    }

    public function testGenerateMakesTheCompleteStructuralInputAvailableToPredicates(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['@', 'NAME']), 1, new ResolvedOutput());
        $generator = new MatchingLexemeGenerator(
            static fn (LexemeInput $context): bool => $context->terminals->nameAt($context->index - 1) === '@',
            new FixedLexemeGenerator('user', 'hostname', 'scanner'),
        );
        $result = $generator->generate($input);
        self::assertNotNull($result);
        self::assertSame('user', [...$result->sequences()][0]->lexemes[0]->text);
    }
}

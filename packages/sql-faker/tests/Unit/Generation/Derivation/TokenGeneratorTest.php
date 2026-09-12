<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Candidate\MatchingLexemeGenerator;
use SqlFaker\Generation\Candidate\RegisteredLexemeGenerator;
use SqlFaker\Generation\Candidate\SequenceLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Candidate\VersionCase;
use SqlFaker\Generation\Candidate\VersionedLexemeGenerator;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\Derivation;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Derivation\TerminationCost;
use SqlFaker\Generation\Derivation\TokenGenerator;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule;

#[CoversClass(TokenGenerator::class)]
#[UsesClass(RewriteRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(ReverseLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(VersionCase::class)]
#[UsesClass(VersionedLexemeGenerator::class)]
#[UsesClass(KeywordPhraseSpacingRule::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingRule::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(ValueLexemeGenerator::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(RegisteredLexemeGenerator::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(SequenceLexemeGenerator::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
final class TokenGeneratorTest extends TestCase
{
    public function testGenerateCanChooseNullableChildrenWhenAnotherSiblingProvidesOutput(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('optional'), new Terminal('UNKNOWN')])]),
            'optional' => new ProductionRule('optional', [new Production([])]),
        ]);
        $generator = new TokenGenerator($grammar, Factory::create(), static fn (string $name): bool => false);
        $sequence = $generator->generate('root', GenerationPlan::all()->requiringNonEmpty());
        self::assertSame(['UNKNOWN'], $sequence->names());
        self::assertCount(2, $sequence->productions);
    }

    public function testGenerateReservesVisibleOutputWithoutConsultingLexicalHandlerAvailability(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new Terminal('EOF')]), new Production([new Terminal('UNKNOWN')])]),
        ]);
        $generator = new TokenGenerator($grammar, Factory::create(), static fn (string $name): bool => $name === 'EOF');
        self::assertSame(['UNKNOWN'], $generator->generate('root', GenerationPlan::all()->requiringNonEmpty())->names());
    }
}

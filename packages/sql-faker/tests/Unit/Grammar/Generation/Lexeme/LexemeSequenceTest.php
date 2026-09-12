<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\Derivation;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Derivation\TerminationCost;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenGenerator;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule;

#[CoversClass(LexemeSequence::class)]
#[UsesClass(RewriteRule::class)]
#[UsesClass(TokenGenerator::class)]
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
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\CompletionWitness::class)]
final class LexemeSequenceTest extends TestCase
{
    public function testKeyDistinguishesOutputAndBoundarySemanticsButNotDefinitionIds(): void
    {
        $origin = new TerminalOccurrence('X', 4);
        $a = new LexemeSequence([new Lexeme('x', 'identifier', $origin, 'one')], 'one');
        $b = new LexemeSequence([new Lexeme('x', 'identifier', $origin, 'two')], 'two');
        $c = new LexemeSequence($a->lexemes, 'one', new SpacingConstraint(SpacingConstraint::JOIN));
        $d = new LexemeSequence([new Lexeme('x', 'function', $origin, 'one')], 'one');
        self::assertSame($a->key(), $b->key());
        self::assertNotSame($a->key(), $c->key());
        self::assertNotSame($a->key(), $d->key());
    }

    public function testSourcesRetainsAlternativeDefinitionsLazily(): void
    {
        $choice = new ChoiceLexemeGenerator(
            new FixedLexemeGenerator('x', 'identifier', 'first'),
            new FixedLexemeGenerator('x', 'identifier', 'second'),
            new FixedLexemeGenerator('y', 'identifier', 'third'),
        );
        $result = $choice->generate(new LexemeInput(TerminalSequence::fromNames(['X']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertCount(2, $candidates);
        self::assertSame(['first:x', 'second:x'], [...$candidates[0]->sources()]);
        self::assertSame(['third:y'], [...$candidates[1]->sources()]);
    }
    public function testKeyTreatsAnExplicitUnrestrictedBoundaryAsEquivalentToAnOmittedOne(): void
    {
        $origin = new TerminalOccurrence('X', 1);
        $lexeme = new Lexeme('X', 'keyword', $origin, 'source');
        $plain = new LexemeSequence([$lexeme], 'plain');
        $unrestricted = new LexemeSequence([$lexeme], 'explicit', new SpacingConstraint(), [1 => new SpacingConstraint()]);
        self::assertSame($plain->key(), $unrestricted->key());
        $left = new LexemeSequence([$lexeme], 'left', new SpacingConstraint(SpacingConstraint::JOIN));
        $boundary = new LexemeSequence([$lexeme], 'zero', null, [0 => new SpacingConstraint(SpacingConstraint::JOIN)]);
        self::assertSame($left->key(), $boundary->key());
        self::assertNotSame($plain->key(), $left->key());
    }
}

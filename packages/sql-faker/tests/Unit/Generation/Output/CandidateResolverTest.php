<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Output;

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

#[CoversClass(CandidateResolver::class)]
#[UsesClass(RewriteRule::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(TerminalOccurrence::class)]
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
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\CompletionWitness::class)]
final class CandidateResolverTest extends TestCase
{
    public function testResolvePropagatesConditionsAcrossAnEmptyMarkerWithoutWritingSpaces(): void
    {
        $terminal = new TerminalOccurrence('NAME', 2);
        $right = new ResolvedOutput([new OutputPart(new Lexeme('name', 'identifier', $terminal, 'name'), '', 'name', [])], new SpacingConstraint(SpacingConstraint::JOIN, ['variable']));
        $resolver = new CandidateResolver(new CombinedSpacingRule());
        $input = new LexemeInput(TerminalSequence::fromNames(['@', 'EOF', 'NAME']), 1, $right);
        $output = $resolver->resolve(new LexemeSequence([], 'eof'), $input);
        self::assertInstanceOf(ResolvedOutput::class, $output);
        self::assertSame($right->parts, $output->parts);
        self::assertSame($right->left, $output->left);
        $joined = $resolver->resolve(new LexemeSequence([new Lexeme('@', 'symbol', new TerminalOccurrence('@', 0), 'at')], 'at'), new LexemeInput($input->terminals, 0, $output));
        self::assertInstanceOf(ResolvedOutput::class, $joined);
        self::assertSame('@name', (new SqlSerializer())->serialize($joined->pieces()));
        self::assertSame(' ', (new SpacingConstraint())->separator());
    }

    public function testResolveIntersectsInternalAndExternalConstraintsWithoutChangingTheRightState(): void
    {
        $terminals = TerminalSequence::fromNames(['PHRASE', ')']);
        $right = new ResolvedOutput([new OutputPart(new Lexeme(')', 'symbol', $terminals->terminals[1], 'close'), '', 'close', [])]);
        $candidate = new LexemeSequence([
            new Lexeme('A', 'keyword', $terminals->terminals[0], 'a'),
            new Lexeme('B', 'keyword', $terminals->terminals[0], 'b'),
        ], 'phrase', null, [1 => new SpacingConstraint(SpacingConstraint::SPACE, ['internal']), 2 => new SpacingConstraint(SpacingConstraint::JOIN, ['external'])]);
        $result = (new CandidateResolver(new CombinedSpacingRule()))->resolve($candidate, new LexemeInput($terminals, 0, $right));
        self::assertInstanceOf(ResolvedOutput::class, $result);
        self::assertSame('A B)', (new SqlSerializer())->serialize($result->pieces()));
        self::assertSame([')', ''], $right->pieces());
        self::assertNull($right->left);
    }

    public function testResolveReportsContradictionsWithBothRuleSources(): void
    {
        $terminals = TerminalSequence::fromNames(['X', 'Y']);
        $right = new ResolvedOutput([new OutputPart(new Lexeme('Y', 'symbol', $terminals->terminals[1], 'y'), '', 'y', [])], new SpacingConstraint(SpacingConstraint::JOIN, ['right-join']));
        $spacing = $this->createMock(SpacingRule::class);
        $spacing->method('apply')->willReturn(new SpacingConstraint(SpacingConstraint::SPACE, ['word-space']));
        $result = (new CandidateResolver($spacing))->resolve(new LexemeSequence([new Lexeme('X', 'symbol', $terminals->terminals[0], 'x')], 'x'), new LexemeInput($terminals, 0, $right));
        self::assertInstanceOf(SpacingConstraint::class, $result);
        self::assertNull($result->separator());
        self::assertSame(['right-join', 'word-space'], $result->rules);
        self::assertSame(SpacingConstraint::JOIN, $right->left?->allowed);
    }

    public function testResolveRejectsAnOwedNeighborAtEitherEndOfTheInput(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['X']), 0, new ResolvedOutput());
        $lexeme = new Lexeme('X', 'symbol', $input->terminal(), 'x');
        $resolver = new CandidateResolver(new CombinedSpacingRule());
        $left = $resolver->resolve(new LexemeSequence([$lexeme], 'x', new SpacingConstraint(SpacingConstraint::JOIN, ['left'])), $input);
        $right = $resolver->resolve(new LexemeSequence([$lexeme], 'x', null, [1 => new SpacingConstraint(SpacingConstraint::SPACE, ['right'])]), $input);
        self::assertInstanceOf(SpacingConstraint::class, $left);
        self::assertInstanceOf(SpacingConstraint::class, $right);
        self::assertSame(['left', 'missing-left-neighbor'], $left->rules);
        self::assertSame(['right', 'missing-right-neighbor'], $right->rules);
    }
}

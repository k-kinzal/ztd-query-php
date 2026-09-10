<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Output;

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
use SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule;
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
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
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

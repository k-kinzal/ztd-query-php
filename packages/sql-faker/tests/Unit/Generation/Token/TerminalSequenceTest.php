<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Token;

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

#[CoversClass(TerminalSequence::class)]
#[UsesClass(RewriteRule::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(ProductionOccurrence::class)]
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
final class TerminalSequenceTest extends TestCase
{
    public function testFromNamesPreservesOccurrenceIdentityForRepeatedTerminalNames(): void
    {
        $sequence = TerminalSequence::fromNames(['IDENT', '.', 'IDENT']);
        self::assertSame([0, 1, 2], array_map(static fn (TerminalOccurrence $terminal): int => $terminal->id, $sequence->terminals));
        self::assertSame($sequence->terminals, $sequence->original);
        self::assertSame([], $sequence->rewrites);
    }

    public function testNamesAndNameAtReadCurrentOutputAndHandleItsEdges(): void
    {
        $sequence = TerminalSequence::fromNames(['A', 'B']);
        self::assertSame(['A', 'B'], $sequence->names());
        self::assertSame('B', $sequence->nameAt(1));
        self::assertNull($sequence->nameAt(-1));
        self::assertNull($sequence->nameAt(2));
    }

    public function testNameAtHandlesAnEmptySequence(): void
    {
        self::assertNull(TerminalSequence::fromNames([])->nameAt(0));
    }

    public function testReplaceRetainsOriginalDerivationAndRecordsTheRule(): void
    {
        $sequence = TerminalSequence::fromNames(['A', 'B']);
        $replacement = $sequence->terminals[1]->replaced('C', 'rewrite');
        $result = $sequence->replace(1, 1, [$replacement], 'rewrite');
        self::assertSame(['A', 'C'], $result->names());
        self::assertSame(['A', 'B'], $sequence->names());
        self::assertSame($sequence->original, $result->original);
        self::assertSame(['rewrite'], $result->rewrites);
        self::assertSame(1, $result->terminals[1]->id);
    }

    public function testRangeAndOccurrencesRetainEmptyAndNestedProductions(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('child'), new NonTerminal('child')]), 0);
        $trace->expand(0, new Production([]), 1);
        $trace->expand(0, new Production([new Terminal('X')]), 2);
        $sequence = $trace->terminals();
        self::assertSame([2, 1], $sequence->occurrences('child'));
        self::assertSame([0, 1], $sequence->range(0));
        self::assertSame([0, 1], $sequence->range(2));
        self::assertNull($sequence->range(1));
        self::assertSame([], $sequence->occurrences('absent'));
    }

    public function testOccurrencesAfterRewritingStillDescribeTheOriginalSelection(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new Terminal('X')]), 3);
        $sequence = $trace->terminals()->replace(0, 1, [], 'remove');
        self::assertSame([0], $sequence->occurrences('root'));
        self::assertNull($sequence->range(0));
        self::assertCount(1, $sequence->original);
        self::assertSame(3, $sequence->productions[0]->ordinal);
    }

    public function testInsertedAllocatesDistinctIdsOutsideTheOriginalDerivation(): void
    {
        $sequence = TerminalSequence::fromNames(['X']);
        $first = $sequence->inserted('A', $sequence->terminals[0], 'insert');
        $second = $sequence->inserted('B', $sequence->terminals[0], 'insert', 1);
        $rewritten = $sequence->replace(0, 0, [$first, $second], 'insert');
        $third = $rewritten->inserted('C', $sequence->terminals[0], 'insert');
        self::assertSame([-1, -2, -3], [$first->id, $second->id, $third->id]);
        self::assertSame('insert', $third->rewrite);
    }
    public function testChildFindsEmptyDirectChildrenWithoutConfusingNestedNames(): void
    {
        $sequence = new TerminalSequence([], [], [], [
            new ProductionOccurrence(0, null, 'root', 0),
            new ProductionOccurrence(1, 0, 'child', 0),
            new ProductionOccurrence(2, 1, 'child', 1),
        ]);
        self::assertSame(1, $sequence->child(0, 'child')?->id);
        self::assertSame(2, $sequence->child(1, 'child')?->id);
        self::assertNull($sequence->child(0, 'missing'));
    }

    public function testInsertedForAttachesOutputToAnOriginallyEmptyProduction(): void
    {
        $sequence = new TerminalSequence([], [], [], [
            new ProductionOccurrence(0, null, 'root', 0),
            new ProductionOccurrence(1, 0, 'optional', 0),
        ]);
        $terminal = $sequence->insertedFor('VALUE', 1, 'complete');
        self::assertSame([0, 1], $terminal->ancestors);
        self::assertSame(['root', 'optional'], $terminal->rules);
        self::assertSame(-1, $terminal->id);
        $output = $sequence->replace(0, 0, [$terminal], 'complete');
        self::assertSame([0, 1], $output->range(1));
        self::assertSame([], $output->original);
        self::assertSame($sequence->productions, $output->productions);
    }

    public function testRangeIncludesAllRetainedTerminalsAndReplacementPreservesEarlierRewrites(): void
    {
        $prefix = new TerminalOccurrence('PREFIX', 1);
        $first = new TerminalOccurrence('A', 2, [0], ['root']);
        $second = new TerminalOccurrence('B', 3, [0], ['root']);
        $tail = new TerminalOccurrence('TAIL', 4);
        $input = new TerminalSequence([$prefix, $first, $second, $tail], [], ['previous']);
        self::assertSame([1, 3], $input->range(0));
        $result = $input->replace(1, 1, [], 'remove');
        self::assertSame([1, 2], $result->range(0));
        self::assertSame(['previous', 'remove'], $result->rewrites);
        self::assertSame(['previous'], $input->rewrites);
        self::assertSame([$prefix, $second, $tail], $result->terminals);
    }
    public function testReplaceRetainsEachIntermediateOperation(): void
    {
        $input = TerminalSequence::fromNames(['A', 'B']);
        $middle = $input->terminals[0]->replaced('C', 'first');
        $first = $input->replace(0, 1, [$middle], 'first');
        $result = $first->replace(0, 1, [], 'second');
        self::assertSame([
            ['rule' => 'first', 'offset' => 0, 'removed' => [$input->terminals[0]], 'inserted' => [$middle]],
            ['rule' => 'second', 'offset' => 0, 'removed' => [$middle], 'inserted' => []],
        ], $result->operations);
        self::assertSame([], $input->operations);
    }
}

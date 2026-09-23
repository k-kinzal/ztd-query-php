<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Seed;

use Closure;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\BytePlanEncoder;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Coverage\CoverageSets;
use SqlFaker\Generation\Coverage\GenerationTrace;
use SqlFaker\Generation\Coverage\GeneratorRevision;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Coverage\GrammarCoverageInventory;
use SqlFaker\Generation\Coverage\LexicalObservation;
use SqlFaker\Generation\Coverage\SequenceObservation;
use SqlFaker\Generation\Derivation\Completion\CompletionWitness;
use SqlFaker\Generation\Derivation\Completion\PatternProductions;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\CompletionFrontier;
use SqlFaker\Generation\Derivation\CompletionMemo;
use SqlFaker\Generation\Derivation\CompletionReduction;
use SqlFaker\Generation\Derivation\CompletionState;
use SqlFaker\Generation\Derivation\ConstrainedCompletion;
use SqlFaker\Generation\Derivation\ConstraintDependencies;
use SqlFaker\Generation\Derivation\Derivation;
use SqlFaker\Generation\Derivation\DerivationNode;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Derivation\TerminationCost;
use SqlFaker\Generation\Derivation\TokenGenerator;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\LexicalGrammar;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Output\BoundaryCompletion;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Seed\CoverageSeed;
use SqlFaker\Generation\Seed\ProductionGraph;
use SqlFaker\Generation\Seed\ProductionGuide;
use SqlFaker\Generation\Seed\SeedCorpus;
use SqlFaker\Generation\Seed\SeedCorpusBuilder;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Value\CharacterDomain;
use SqlFaker\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(SeedCorpusBuilder::class)]
#[UsesClass(CoverageSeed::class)]
#[UsesClass(ProductionGraph::class)]
#[UsesClass(ProductionGuide::class)]
#[UsesClass(SeedCorpus::class)]
#[UsesClass(SqlGenerator::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(GrammarCoverageInventory::class)]
#[UsesClass(GeneratorRevision::class)]
#[UsesClass(GenerationTrace::class)]
#[UsesClass(CoverageSets::class)]
#[UsesClass(LexicalObservation::class)]
#[UsesClass(SequenceObservation::class)]
#[UsesClass(DerivationNode::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(PlanBuilder::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(BytePlanCompiler::class)]
#[UsesClass(BytePlanEncoder::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(ValueLexemeGenerator::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(ReverseLexemeGenerator::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(ConstrainedCompletion::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(BoundaryCompletion::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(CompletionReduction::class)]
#[UsesClass(ConstraintDependencies::class)]
#[UsesClass(PatternProductions::class)]
#[UsesClass(CompletionWitness::class)]
#[UsesClass(CharacterDomain::class)]
final class SeedCorpusBuilderTest extends TestCase
{
    public function testBuildReachesEveryProductionBelowTheRootWithReplayableInputs(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('version')->willReturn('test-v1');
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('SELECT', $literalDomain, ['SELECT'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('DELETE', $literalDomain, ['DELETE'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('1', $literalDomain, ['1'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('+', $literalDomain, ['+'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('AS', $literalDomain, ['AS'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('name', $literalDomain, ['name'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $generator = new SqlGenerator(
            (new Grammar('stmt', [
                'stmt' => new ProductionRule('stmt', [
                    new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                    new Production([new Terminal('DELETE')]),
                ]),
                'expr' => new ProductionRule('expr', [new Production([new Terminal('1')]), new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')])]),
                'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
            ]))->identified(),
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $builder = new SeedCorpusBuilder($coverage, $generator->planner(), $generator->generate(...));

        $corpus = $builder->build('stmt');

        self::assertSame([], $corpus->failures);
        self::assertSame([], $corpus->unreached());
        self::assertSame(['stmt#0', 'stmt#1', 'expr#0', 'expr#1', 'tail#0', 'tail#1'], $corpus->reached());
        self::assertSame(['SELECT 1', 'DELETE', 'SELECT 1 + 1', 'SELECT 1 AS name'], array_map(static fn (CoverageSeed $seed): string => $seed->sql, $corpus->seeds));
        self::assertSame(['stmt-0', 'stmt-1', 'expr-1', 'tail-1'], array_map(static fn (CoverageSeed $seed): string => $seed->name(), $corpus->seeds));
        self::assertSame(5, $corpus->maximumBudget());
        self::assertSame(
            'SELECT 1 + 1',
            $generator->generate((new BytePlanCompiler())->compile($corpus->seeds[2]->input, $generator->planner(), GenerationPlan::fromRule('stmt')->requiringNonEmpty())),
        );
    }

    public function testBuildRecordsProductionsNoWalkWithinTheCapReaches(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('version')->willReturn('test-v1');
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('SELECT', $literalDomain, ['SELECT'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('DELETE', $literalDomain, ['DELETE'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('1', $literalDomain, ['1'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $generator = new SqlGenerator(
            (new Grammar('stmt', [
                'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])]),
                'expr' => new ProductionRule('expr', [new Production([new Terminal('1')])]),
            ]))->identified(),
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $builder = new SeedCorpusBuilder($coverage, $generator->planner(), $generator->generate(...));

        $corpus = $builder->build('stmt', 1);

        self::assertSame(['stmt#0', 'expr#0'], $corpus->unreached());
        self::assertSame(['stmt#0', 'expr#0'], array_keys($corpus->failures));
        self::assertSame(['DELETE'], array_map(static fn (CoverageSeed $seed): string => $seed->sql, $corpus->seeds));
    }

    public function testSeedAnswersNothingWhenTheRootCannotReachTheProduction(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('version')->willReturn('test-v1');
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(new ValueLexemeGenerator('1', $literalDomain, ['1'], 'fixture', 'fixture-literal')),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $grammar = (new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('expr')])]),
            'expr' => new ProductionRule('expr', [new Production([new Terminal('1')])]),
        ]))->identified();
        $generator = new SqlGenerator($grammar, Factory::create(), $lexical, coverage: $coverage);
        $builder = new SeedCorpusBuilder($coverage, $generator->planner(), $generator->generate(...));

        self::assertNull($builder->seed(
            GenerationPlan::fromRule('expr')->requiringNonEmpty(),
            new ProductionGraph($grammar),
            'stmt',
            0,
            $grammar->ruleMap['stmt']->alternatives[0],
            10,
        ));
    }

    public function testReplayDecodesAnInputAndReportsWhatItExercised(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('version')->willReturn('test-v1');
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('SELECT', $literalDomain, ['SELECT'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('DELETE', $literalDomain, ['DELETE'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('1', $literalDomain, ['1'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $generator = new SqlGenerator(
            (new Grammar('stmt', [
                'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])]),
                'expr' => new ProductionRule('expr', [new Production([new Terminal('1')])]),
            ]))->identified(),
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $builder = new SeedCorpusBuilder($coverage, $generator->planner(), $generator->generate(...));

        $seed = $builder->replay(GenerationPlan::fromRule('stmt')->requiringNonEmpty(), '');

        self::assertSame('DELETE', $seed->sql);
        self::assertSame(1, $seed->budget);
        self::assertNull($seed->rule);
        self::assertSame(hash('sha256', ''), $seed->name());
        self::assertSame(['stmt#1'], (new SeedCorpus('stmt', [$seed], $builder->targets('stmt')))->reached());
        self::assertSame(['stmt#1'], (new SeedCorpus('stmt', [$seed], $builder->targets('stmt')))->emitted());
    }

    public function testTargetsLabelsEveryProductionReachableFromTheRoot(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $generator = new SqlGenerator(
            (new Grammar('stmt', [
                'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])]),
                'expr' => new ProductionRule('expr', [new Production([new Terminal('1')]), new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')])]),
                'other' => new ProductionRule('other', [new Production([new Terminal('OTHER')])]),
            ]))->identified(),
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $builder = new SeedCorpusBuilder($coverage, $generator->planner(), $generator->generate(...));

        self::assertSame(['stmt#0', 'stmt#1', 'expr#0', 'expr#1'], array_values($builder->targets('stmt')));
        self::assertSame(['expr#0', 'expr#1'], array_values($builder->targets('expr')));
    }
}

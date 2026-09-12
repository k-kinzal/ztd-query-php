<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Choice;

use Closure;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\PlanBuilder;
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
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
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
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalMappingRule;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Token\TokenRewriter;
use SqlFaker\Generation\Value\CharacterDomain;
use SqlFaker\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(PlanBuilder::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(DerivationNode::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(SqlGenerator::class)]
#[UsesClass(DerivationTrace::class)]
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
#[UsesClass(SqlSerializer::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalMappingRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(ConstrainedCompletion::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(BoundaryCompletion::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(CompletionReduction::class)]
#[UsesClass(ConstraintDependencies::class)]
#[UsesClass(BytePlanCompiler::class)]
#[UsesClass(PatternProductions::class)]
#[UsesClass(CompletionWitness::class)]
#[UsesClass(CharacterDomain::class)]
final class PlanBuilderTest extends TestCase
{
    public function testRootResolvesOnlyAnExplicitReleaseAlias(): void
    {
        $builder = new PlanBuilder(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [
                            new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                            new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]),
                        ],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                        ],
                    ),
                    'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
                ],
            ))->identified(),
            $this->createMock(LexicalGrammar::class),
            startSymbol: static fn (?string $requested): string => 'expr',
        );
        self::assertSame('stmt', $builder->root(GenerationPlan::all()));
        self::assertSame('expr', $builder->root(GenerationPlan::fromRule('alias')));
    }

    public function testMinimumExpansionsKeepsTheNonEmptyRequirementSeparateFromNullability(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([]), new Production([new NonTerminal('leaf')])]),
                'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
            ],
        );
        $builder = new PlanBuilder($grammar, $this->createMock(LexicalGrammar::class));
        self::assertSame(1, $builder->minimumExpansions(GenerationPlan::all()));
        self::assertSame(2, $builder->minimumExpansions(GenerationPlan::all()->requiringNonEmpty()));
    }

    public function testMinimumExpansionsIncludesTheSelectedRootAlternativeBeforeAllocatingInputBudget(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new Terminal('T')]), new Production([new NonTerminal('leaf')])]),
                'leaf' => new ProductionRule('leaf', [new Production([new NonTerminal('end')])]),
                'end' => new ProductionRule('end', [new Production([new Terminal('U')])]),
            ],
        );
        $lexical = $this->createMock(LexicalGrammar::class);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('T', $literalDomain, ['T'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['U'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $builder = new PlanBuilder($grammar, $lexical);
        $constraints = GenerationPlan::constrained('root', ['root' => [ProductionPattern::at(1)]])->requiringNonEmpty()->withExpansionBudget(5);
        self::assertSame(3, $builder->minimumExpansions($constraints));
        $plan = (new BytePlanCompiler())->compile('', $builder, $constraints);
        self::assertSame(3, $plan->expansionBudget());
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('root', 0));
        self::assertSame('U', (new SqlGenerator($grammar, Factory::create(), $lexical))->generate($plan));
    }

    public function testMinimumExpansionsRetainsNonEmptyCostsAndTheUnreachableSentinelForRootPatterns(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([]), new Production([new Terminal('T')])])]);
        $builder = new PlanBuilder($grammar, $this->createMock(LexicalGrammar::class));
        $empty = GenerationPlan::constrained('root', ['root' => [ProductionPattern::at(0)]]);
        self::assertSame(1, $builder->minimumExpansions($empty));
        self::assertSame(PHP_INT_MAX, $builder->minimumExpansions($empty->requiringNonEmpty()));
        self::assertSame(
            PHP_INT_MAX,
            $builder->minimumExpansions(GenerationPlan::constrained('root', ['root' => [ProductionPattern::at(5)]])),
        );
        self::assertSame(
            PHP_INT_MAX,
            $builder->minimumExpansions(GenerationPlan::constrained('missing', ['missing' => [ProductionPattern::at(0)]])),
        );
    }

    public function testBuildUsesTheSameContextualRewriteAndPinsItsCompleteCandidate(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new Terminal('T')])])]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('CONTEXTUAL', $literalDomain, ['CONTEXTUAL'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['U'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $rewriter = new TokenRewriter(new TerminalMappingRule('root', 'T', 'CONTEXTUAL', 'fixture'));
        $constraints = GenerationPlan::all()->withLexemes(['T' => ['explicit']]);
        $plan = (new PlanBuilder($grammar, $lexical, $rewriter))->build($constraints, 1, static fn (int $count): int => 0, static fn (int $count): int => $count - 1);
        self::assertSame('explicit', $plan->lexemeAt('CONTEXTUAL', 0));
        self::assertNotNull($plan->candidateKeyAt('CONTEXTUAL', 0));
        self::assertNull($plan->startRule());
        $generator = new SqlGenerator($grammar, Factory::create(), $lexical, $rewriter);
        self::assertSame('explicit', $generator->generate($plan));
        self::assertSame('explicit', $generator->generate($plan));
    }

    public function testBuildPreservesRepeatedProductionAndLexemeConstraints(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf'), new NonTerminal('leaf'), new NonTerminal('leaf')])]),
                'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')]), new Production([new Terminal('U')])]),
            ],
        );
        $lexical = $this->createMock(LexicalGrammar::class);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('T', $literalDomain, ['first', 'second'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['first', 'second'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $constraints = GenerationPlan::constrained('root', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(0), ProductionPattern::at(1)]])->withLexemes(['T' => ['first', 'second'], 'U' => ['third']]);
        $plan = (new PlanBuilder($grammar, $lexical))->build($constraints, 4, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('leaf', 0));
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('leaf', 1));
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('leaf', 2));
        self::assertSame('first', $plan->lexemeAt('T', 0));
        self::assertSame('second', $plan->lexemeAt('T', 1));
        self::assertSame('third', $plan->lexemeAt('U', 0));
    }

    public function testBuildPreservesEmptyMarkerCandidatesWithoutLosingNonEmptyCompletion(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new Terminal('END'), new NonTerminal('leaf')])]),
                'leaf' => new ProductionRule('leaf', [new Production([]), new Production([new Terminal('T')])]),
            ],
        );
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturnCallback(static fn (string $name): bool => $name === 'END');
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('T', $literalDomain, ['T'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('END', $literalDomain, [''], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $builder = new PlanBuilder($grammar, $lexical);
        $empty = $builder->build(GenerationPlan::all(), 2, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        $nonEmpty = $builder->build(
            GenerationPlan::all()->requiringNonEmpty(),
            2,
            static fn (int $count): ?int => null,
            static fn (int $count): ?int => null,
        );
        self::assertSame('', $empty->lexemeAt('END', 0));
        self::assertNotNull($empty->candidateKeyAt('END', 0));
        self::assertNull($empty->lexemeAt('T', 0));
        self::assertSame('T', $nonEmpty->lexemeAt('T', 0));
    }

    public function testBuildReservesAllPendingSiblingsAndRetainsTheOriginalOrdinal(): void
    {
        $grammar = (new Grammar(
            'stmt',
            [
                'stmt' => new ProductionRule(
                    'stmt',
                    [
                        new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                        new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]),
                    ],
                ),
                'expr' => new ProductionRule(
                    'expr',
                    [
                        new Production([new Terminal('1')]),
                        new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                    ],
                ),
                'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
            ],
        ))->identified();
        $lexical = $this->createMock(LexicalGrammar::class);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('SELECT', $literalDomain, ['SELECT'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('DELETE', $literalDomain, ['DELETE'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('FROM', $literalDomain, ['FROM'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('missing', $literalDomain, ['missing'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('1', $literalDomain, ['1'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('+', $literalDomain, ['+'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('AS', $literalDomain, ['AS'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('name', $literalDomain, ['name'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('CHANGED', $literalDomain, ['CHANGED'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $constraints = GenerationPlan::constrained('stmt', ['stmt' => [ProductionPattern::at(0)]])->requiringNonEmpty();
        $plan = (new PlanBuilder($grammar, $lexical))->build($constraints, 3, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('tail', 0));
        self::assertSame('SELECT 1', (new SqlGenerator($grammar, Factory::create(), $lexical))->generate($plan));
    }

    public function testBuildPropagatesMissingCandidateFailuresWithoutRetrying(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->expects(self::once())->method('resolveSequence')->willThrowException(new LexicalException('missing candidate'));
        $builder = new PlanBuilder(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [
                            new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                            new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]),
                        ],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                        ],
                    ),
                    'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
                ],
            ))->identified(),
            $lexical,
        );
        $this->expectException(LexicalException::class);
        $builder->build(GenerationPlan::all(), 5, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
    }

    public function testBuildReportsAnUnknownRequestedRule(): void
    {
        $builder = new PlanBuilder(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [
                            new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                            new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]),
                        ],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                        ],
                    ),
                    'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
                ],
            ))->identified(),
            $this->createMock(LexicalGrammar::class),
        );
        $this->expectException(GenerationException::class);
        $builder->build(GenerationPlan::fromRule('missing'), 5, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
    }

    public function testMinimumExpansionsIncludesTheConstrainedDescendantBeforeDrawingTheBudget(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new NonTerminal('child')])]),
                'child' => new ProductionRule('child', [new Production([new Terminal('T')]), new Production([new NonTerminal('leaf')])]),
                'leaf' => new ProductionRule('leaf', [new Production([new Terminal('U')])]),
            ],
        );
        $lexical = $this->createMock(LexicalGrammar::class);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('T', $literalDomain, ['T'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['U'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $builder = new PlanBuilder($grammar, $lexical);
        $constraints = GenerationPlan::constrained('root', ['child' => [ProductionPattern::at(1)]])->requiringNonEmpty()->withExpansionBudget(5);
        self::assertSame(3, $builder->minimumExpansions($constraints));
        $plan = (new BytePlanCompiler())->compile('', $builder, $constraints);
        self::assertSame(3, $plan->expansionBudget());
        self::assertSame('U', (new SqlGenerator($grammar, Factory::create(), $lexical))->generate($plan));
    }
}

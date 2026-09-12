<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Closure;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageSets;
use SqlFaker\Coverage\GenerationTrace;
use SqlFaker\Coverage\GeneratorRevision;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Coverage\LexicalObservation;
use SqlFaker\Coverage\SequenceObservation;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Choice\ByteChoices;
use SqlFaker\Grammar\Choice\BytePlanCompiler;
use SqlFaker\Grammar\Derivation\Completion\CompletionWitness;
use SqlFaker\Grammar\Derivation\Completion\PatternProductions;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\CompletionFrontier;
use SqlFaker\Grammar\Derivation\CompletionMemo;
use SqlFaker\Grammar\Derivation\CompletionReduction;
use SqlFaker\Grammar\Derivation\CompletionState;
use SqlFaker\Grammar\Derivation\ConstrainedCompletion;
use SqlFaker\Grammar\Derivation\ConstraintDependencies;
use SqlFaker\Grammar\Derivation\Derivation;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\PlanBuilder;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Derivation\TerminationCost;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\BoundaryCompletion;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenGenerator;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ValueChoices;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(SqlGenerator::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(ProductionPattern::class)]
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
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(PlanBuilder::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(GrammarCoverageInventory::class)]
#[UsesClass(GeneratorRevision::class)]
#[UsesClass(GenerationTrace::class)]
#[UsesClass(CoverageSets::class)]
#[UsesClass(SequenceObservation::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(ConstrainedCompletion::class)]
#[UsesClass(LexicalObservation::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(BoundaryCompletion::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(CompletionReduction::class)]
#[UsesClass(ConstraintDependencies::class)]
#[UsesClass(BytePlanCompiler::class)]
#[UsesClass(PatternProductions::class)]
#[UsesClass(CompletionWitness::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(FixedLexemeGenerator::class)]
final class SqlGeneratorTest extends TestCase
{
    public function testGenerateRecordsACompleteCoverageObservation(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('version')->willReturn('test-v1');
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
        $generator = new SqlGenerator(
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
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $sql = $generator->generate(GenerationPlan::all()->withMaxDepth(1)->withStepBudget()->withExpansionBudget(17));
        self::assertSame('DELETE FROM missing', $sql);
        $trace = $coverage->lastGeneration();
        self::assertNotNull($trace);
        self::assertSame('stmt', $trace['root']);
        self::assertSame(['budget' => 17, 'lexicalTarget' => null], $trace['planSummary']);
        self::assertSame('success', $trace['status']);
        self::assertCount(1, $trace['attempts']);
        self::assertSame(0, $trace['attempts'][0]['id']);
        self::assertSame('committed', $trace['attempts'][0]['status']);
        self::assertSame(hash('sha256', $sql), $trace['attempts'][0]['sqlHash']);
        self::assertCount(1, $trace['reachedIds']);
        self::assertSame($trace['reachedIds'], $trace['emittedIds']);
        self::assertCount(3, $trace['lexicalEvents']);
        self::assertCount(3, $trace['spacingEvents']);
        self::assertSame(1, $coverage->snapshot()['checkpoint']['generationsObservedInRun']);
        self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
    }

    public function testGenerateKeepsRewrittenSourcesReachedWithoutCreditingTheirOutput(): void
    {
        $coverage = new GrammarCoverage();
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
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(
            static fn (TerminalSequence $sequence): TerminalSequence => $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('CHANGED', 'fixture.change')], 'fixture.change'),
        );
        $generator = new SqlGenerator(
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
            Factory::create(),
            $lexical,
            new TokenRewriter($rule),
            coverage: $coverage,
        );
        self::assertSame('CHANGED FROM missing', $generator->generate(GenerationPlan::all()->withMaxDepth(1)->withStepBudget()));
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame(['fixture.change'], $coverage->lastGeneration()['rewrites']);
        self::assertSame(1, $coverage->snapshot()['current']['reached']);
        self::assertSame(0, $coverage->snapshot()['current']['emitted']);
    }

    public function testGenerateClosesCoverageAfterARejectedEmptyLexicalResult(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('generate')->willReturn('');
        $generator = new SqlGenerator(
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
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $this->expectException(GenerationException::class);
        try {
            $generator->generate(GenerationPlan::lexical('identifier', [])->requiringNonEmpty());
        } finally {
            self::assertNotNull($coverage->lastGeneration());
            self::assertSame('failed', $coverage->lastGeneration()['status']);
            self::assertSame('discarded', $coverage->lastGeneration()['attempts'][0]['status']);
            self::assertSame([], $coverage->lastGeneration()['emittedIds']);
            self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
        }
    }

    public function testGenerateReplacesGrammarCoverageWithTheLatestLexicalTrace(): void
    {
        $coverage = new GrammarCoverage();
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
        $lexical->method('generate')->willReturn('name');
        $generator = new SqlGenerator(
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
            Factory::create(),
            $lexical,
            coverage: $coverage,
        );
        $generator->generate(GenerationPlan::all()->withMaxDepth(1)->withStepBudget());
        self::assertSame('name', $generator->generate(GenerationPlan::lexical('identifier', [])));
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame(2, $coverage->lastGeneration()['generationId']);
        self::assertSame(['budget' => null, 'lexicalTarget' => 'identifier'], $coverage->lastGeneration()['planSummary']);
        self::assertSame(['identifier'], $coverage->lastGeneration()['lexicalEvents']);
        self::assertSame([], $coverage->lastGeneration()['reachedIds']);
        self::assertSame([], $coverage->lastGeneration()['emittedIds']);
        self::assertSame(hash('sha256', 'name'), $coverage->lastGeneration()['attempts'][0]['sqlHash']);
        self::assertSame('success', $coverage->lastGeneration()['status']);
        self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
    }

    public function testRealizeOffersTheWholeZeroBasedCandidateRange(): void
    {
        $faker = Factory::create();
        $faker->seed(19);
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('resolveSequence')->willReturnCallback(
            /**
             * @param GenerationPlan<bool> $plan
             * @param Closure(int): int $choose
             */
            static function (TerminalSequence $sequence, GenerationPlan $plan, Closure $choose) {
                $choices = array_map($choose, array_fill(0, 32, 2));
                self::assertContains(0, $choices);
                self::assertContains(1, $choices);
                self::assertSame([], array_filter($choices, static fn (mixed $choice): bool => $choice !== 0 && $choice !== 1));
                self::assertSame(0, $choose(1));
                return (new ReverseLexemeGenerator(
                    new FixedLexemeGenerator('T', 'fixture', 'fixture-literal'),
                    new CandidateResolver(new CombinedSpacingRule()),
                    'fixture',
                ))->generate(TerminalSequence::fromNames(['T']), null, static fn (int $count): int => 0);
            },
        );
        self::assertSame('T', (new SqlGenerator($grammar, $faker, $lexical))->realize('stmt', GenerationPlan::all()));
    }

    public function testGenerateReusesCompletionAnalysisAcrossDifferentPlans(): void
    {
        $grammar = new Grammar(
            'first',
            [
                'first' => new ProductionRule('first', [new Production([new Terminal('T')])]),
                'second' => new ProductionRule('second', [new Production([new Terminal('U')])]),
            ],
        );
        $lexer = $this->createMock(LexicalGrammar::class);
        $observations = [];
        $lexer->method('isNonOutput')->willReturnCallback(
            static function (string $terminal) use (&$observations): bool {
                $observations[] = $terminal;
                return false;
            },
        );
        $lexer->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence) => (new ReverseLexemeGenerator(
                new FixedLexemeGenerator(implode(' ', $sequence->names()), 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames([implode(' ', $sequence->names())]), null, static fn (int $count): int => 0),
        );
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        self::assertSame('T', $generator->generate(GenerationPlan::all()));
        self::assertSame(['T', 'U'], $observations);
        self::assertSame('U', $generator->generate(GenerationPlan::fromRule('second')));
        self::assertSame(['T', 'U'], $observations);
        self::assertSame('T', $generator->generate(GenerationPlan::all()));
        self::assertSame(['T', 'U'], $observations);
    }

    public function testGenerateUsesTheGrammarEntryPointWithoutDialectKnowledge(): void
    {
        $grammar = new Grammar('custom_entry', ['custom_entry' => new ProductionRule('custom_entry', [new Production([new Terminal('CUSTOM')])])]);
        $plan = GenerationPlan::all();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['CUSTOM']), $plan)->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('custom sql', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['custom sql']), null, static fn (int $count): int => 0),
        );
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        self::assertSame('custom sql', $generator->generate($plan));
    }

    public function testGenerateUsesExplicitRulesAndSuppliedParserSemantics(): void
    {
        $grammar = new Grammar('other', ['selected' => new ProductionRule('selected', [new Production([new Terminal('RAW')])])]);
        $plan = GenerationPlan::fromRule('selected')->requiringNonEmpty();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['NORMALIZED', 'RAW']), $plan)->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('normalized', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['normalized']), null, static fn (int $count): int => 0),
        );
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(
            static fn (TerminalSequence $sequence): TerminalSequence => $sequence->replace(0, 0, [$sequence->inserted('NORMALIZED', $sequence->terminals[0], 'test.rule')], 'test.rule'),
        );
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer, new TokenRewriter($rule));
        self::assertSame('normalized', $generator->generate($plan));
    }

    public function testGenerateUsesTheSuppliedVersionSpecificRuleResolver(): void
    {
        $grammar = new Grammar('other', ['old_rule' => new ProductionRule('old_rule', [new Production([new Terminal('TOKEN')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['TOKEN']))->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('token', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['token']), null, static fn (int $count): int => 0),
        );
        $generator = new SqlGenerator(
            $grammar,
            Factory::create(),
            $lexer,
            null,
            static fn (?string $rule): string => $rule === 'new_rule' ? 'old_rule' : 'missing',
        );
        self::assertSame('token', $generator->generate(GenerationPlan::fromRule('new_rule')));
    }

    public function testGenerateLexicalPlansBypassGrammarDerivation(): void
    {
        $plan = GenerationPlan::lexical('identifier', []);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('generate')->with($plan)->willReturn('name');
        $lexer->expects(self::never())->method('resolveSequence');
        $generator = new SqlGenerator(new Grammar('missing', []), Factory::create(), $lexer);
        self::assertSame('name', $generator->generate($plan));
    }

    public function testGeneratePreservesTheFirstLexicalFailureWithoutRetrying(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $failure = new LexicalException('last failure');
        $lexer->expects(self::once())->method('resolveSequence')->willThrowException($failure);
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectExceptionObject($failure);
        $generator->generate(GenerationPlan::all());
    }

    public function testGenerateAllowsEmptyOutputWhenThePlanAllowsIt(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['']), null, static fn (int $count): int => 0),
        );
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        self::assertSame('', $generator->generate(GenerationPlan::all()));
    }

    public function testGenerateRejectsUnexpectedEmptyOutputWithoutRetrying(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('version')->willReturn('custom-1');
        $lexer->expects(self::once())->method('resolveSequence')->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['']), null, static fn (int $count): int => 0),
        );
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('custom-1 generation plan requires non-empty output.');
        $generator->generate(GenerationPlan::all()->requiringNonEmpty());
    }

    public function testGenerateClearsPreviousGrammarTraceBeforeALexicalPlan(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('TOKEN')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('resolveSequence')->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('token', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['token']), null, static fn (int $count): int => 0),
        );
        $lexer->method('generate')->willReturn('name');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        self::assertSame('token', $generator->generate(GenerationPlan::all()));
        self::assertSame(['TOKEN'], $generator->lastSequence?->names());
        self::assertSame('name', $generator->generate(GenerationPlan::lexical('identifier', [])));
        self::assertNull($generator->lastSequence);
    }

    public function testGenerateReportsOnlyTheLatestRewrittenDerivation(): void
    {
        $grammar = new Grammar(
            'first',
            [
                'first' => new ProductionRule('first', [new Production([new Terminal('A')])]),
                'second' => new ProductionRule('second', [new Production([new Terminal('B')])]),
            ],
        );
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('resolveSequence')->willReturn(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('output', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['output']), null, static fn (int $count): int => 0),
        );
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(static fn (TerminalSequence $sequence): TerminalSequence => $sequence->replace(0, 0, [], 'observed'));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer, new TokenRewriter($rule));
        $generator->generate(GenerationPlan::all());
        $first = $generator->lastSequence;
        $generator->generate(GenerationPlan::fromRule('second'));
        self::assertSame(['A'], $first?->names());
        self::assertSame(['B'], $generator->lastSequence->names());
        self::assertSame(['observed'], $generator->lastSequence->rewrites);
        self::assertSame('second', $generator->lastSequence->productions[0]->rule);
    }

    public function testPlannerBindsTheSameGrammarEntryAndFreezesEveryChoice(): void
    {
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
        $generator = new SqlGenerator(
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
            Factory::create(),
            $lexical,
        );
        $plan = (new BytePlanCompiler())->compile('', $generator->planner());
        self::assertSame('stmt', $generator->planner()->root($plan));
        self::assertSame($generator->generate($plan), $generator->generate($plan));
    }

    public function testRealizeRetainsTheActualTerminalAndLexicalTrace(): void
    {
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
        $generator = new SqlGenerator(
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
            Factory::create(),
            $lexical,
        );
        self::assertSame('DELETE FROM missing', $generator->realize('stmt', GenerationPlan::all()->withMaxDepth(1)->withStepBudget()));
        self::assertNotNull($generator->lastSequence);
        self::assertSame(['DELETE', 'FROM', 'missing'], $generator->lastSequence->names());
        self::assertNotNull($generator->lastOutput);
        self::assertCount(3, $generator->lastOutput->candidates);
    }
}

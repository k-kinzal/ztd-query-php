<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Closure;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(SqlGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\PlanBuilder::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[UsesClass(\SqlFaker\Coverage\SequenceObservation::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Coverage\LexicalObservation::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\CompletionWitness::class)]
final class SqlGeneratorTest extends TestCase
{
    public function testGenerateRecordsACompleteCoverageObservation(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('version')->willReturn('test-v1');
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $generator = new SqlGenerator(CoverageFixture::syntaxGrammar(), Factory::create(), $lexical, coverage: $coverage);
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
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(static fn (TerminalSequence $sequence): TerminalSequence => $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('CHANGED', 'fixture.change')], 'fixture.change'));
        $generator = new SqlGenerator(CoverageFixture::syntaxGrammar(), Factory::create(), $lexical, new TokenRewriter($rule), coverage: $coverage);
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
        $generator = new SqlGenerator(CoverageFixture::syntaxGrammar(), Factory::create(), $lexical, coverage: $coverage);
        self::assertInstanceOf(GenerationException::class, CoverageFixture::generationFailure($generator, GenerationPlan::lexical('identifier', [])->requiringNonEmpty()));
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('failed', $coverage->lastGeneration()['status']);
        self::assertSame('discarded', $coverage->lastGeneration()['attempts'][0]['status']);
        self::assertSame([], $coverage->lastGeneration()['emittedIds']);
        self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
    }

    public function testGenerateReplacesGrammarCoverageWithTheLatestLexicalTrace(): void
    {
        $coverage = new GrammarCoverage();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $lexical->method('generate')->willReturn('name');
        $generator = new SqlGenerator(CoverageFixture::syntaxGrammar(), Factory::create(), $lexical, coverage: $coverage);
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
                $choices = CoverageFixture::candidateChoices($choose);
                self::assertContains(0, $choices);
                self::assertContains(1, $choices);
                self::assertSame([], array_diff($choices, [0, 1]));
                self::assertSame(0, $choose(1));
                return CoverageFixture::output('T');
            }
        );
        self::assertSame('T', (new SqlGenerator($grammar, $faker, $lexical))->realize('stmt', GenerationPlan::all()));
    }

    public function testGenerateReusesCompletionAnalysisAcrossDifferentPlans(): void
    {
        $grammar = new Grammar('first', [
            'first' => new ProductionRule('first', [new Production([new Terminal('T')])]),
            'second' => new ProductionRule('second', [new Production([new Terminal('U')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $observations = [];
        $lexer->method('isNonOutput')->willReturnCallback(static function (string $terminal) use (&$observations): bool {
            $observations[] = $terminal;
            return false;
        });
        $lexer->method('resolveSequence')->willReturnCallback(static fn (TerminalSequence $sequence) => CoverageFixture::output(implode(' ', $sequence->names())));
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
        $grammar = new Grammar('custom_entry', [
            'custom_entry' => new ProductionRule('custom_entry', [new Production([new Terminal('CUSTOM')])]),
        ]);
        $plan = GenerationPlan::all();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['CUSTOM']), $plan)->willReturn(CoverageFixture::output('custom sql'));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('custom sql', $generator->generate($plan));
    }

    public function testGenerateUsesExplicitRulesAndSuppliedParserSemantics(): void
    {
        $grammar = new Grammar('other', [
            'selected' => new ProductionRule('selected', [new Production([new Terminal('RAW')])]),
        ]);
        $plan = GenerationPlan::fromRule('selected')->requiringNonEmpty();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['NORMALIZED', 'RAW']), $plan)->willReturn(CoverageFixture::output('normalized'));
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(static fn (TerminalSequence $sequence): TerminalSequence =>
            $sequence->replace(0, 0, [$sequence->inserted('NORMALIZED', $sequence->terminals[0], 'test.rule')], 'test.rule'));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer, new TokenRewriter($rule));

        self::assertSame('normalized', $generator->generate($plan));
    }

    public function testGenerateUsesTheSuppliedVersionSpecificRuleResolver(): void
    {
        $grammar = new Grammar('other', [
            'old_rule' => new ProductionRule('old_rule', [new Production([new Terminal('TOKEN')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('resolveSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['TOKEN']))->willReturn(CoverageFixture::output('token'));
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
        $lexer->expects(self::once())->method('resolveSequence')->willReturn(CoverageFixture::output(''));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('', $generator->generate(GenerationPlan::all()));
    }

    public function testGenerateRejectsUnexpectedEmptyOutputWithoutRetrying(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('version')->willReturn('custom-1');
        $lexer->expects(self::once())->method('resolveSequence')->willReturn(CoverageFixture::output(''));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('custom-1 generation plan requires non-empty output.');

        $generator->generate(GenerationPlan::all()->requiringNonEmpty());
    }

    public function testGenerateClearsPreviousGrammarTraceBeforeALexicalPlan(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('TOKEN')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('resolveSequence')->willReturn(CoverageFixture::output('token'));
        $lexer->method('generate')->willReturn('name');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        self::assertSame('token', $generator->generate(GenerationPlan::all()));
        self::assertSame(['TOKEN'], $generator->lastSequence?->names());
        self::assertSame('name', $generator->generate(GenerationPlan::lexical('identifier', [])));
        self::assertNull($generator->lastSequence);
    }

    public function testGenerateReportsOnlyTheLatestRewrittenDerivation(): void
    {
        $grammar = new Grammar('first', [
            'first' => new ProductionRule('first', [new Production([new Terminal('A')])]),
            'second' => new ProductionRule('second', [new Production([new Terminal('B')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('resolveSequence')->willReturn(CoverageFixture::output('output'));
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
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $generator = new SqlGenerator(CoverageFixture::syntaxGrammar(), Factory::create(), $lexical);
        $plan = GenerationPlan::fromBytes('', $generator->planner());
        self::assertSame('stmt', $generator->planner()->root($plan));
        self::assertSame($generator->generate($plan), $generator->generate($plan));
    }

    public function testRealizeRetainsTheActualTerminalAndLexicalTrace(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $generator = new SqlGenerator(CoverageFixture::syntaxGrammar(), Factory::create(), $lexical);
        self::assertSame('DELETE FROM missing', $generator->realize('stmt', GenerationPlan::all()->withMaxDepth(1)->withStepBudget()));
        self::assertNotNull($generator->lastSequence);
        self::assertSame(['DELETE', 'FROM', 'missing'], $generator->lastSequence->names());
        self::assertNotNull($generator->lastOutput);
        self::assertCount(3, $generator->lastOutput->candidates);
    }
}

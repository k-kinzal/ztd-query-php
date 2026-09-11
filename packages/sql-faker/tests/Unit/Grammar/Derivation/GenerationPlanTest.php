<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\PlanBuilder;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(PlanBuilder::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\CompletionWitness::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
final class GenerationPlanTest extends TestCase
{
    /**
     * @param GenerationPlan<bool>|null $constraints
     */
    #[DataProvider('providerInputBudgets')]
    public function testFromBytesMapsTheHeaderIntoTheAllowedExpansionRange(string $input, ?GenerationPlan $constraints, int $expected): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $lexical->method('resolveSequence')->willReturnCallback(\Tests\Fixtures\SqlFaker\CoverageFixture::resolve(...));
        $plan = GenerationPlan::fromBytes($input, new PlanBuilder($grammar, $lexical), $constraints);

        self::assertSame($expected, $plan->expansionBudget());
        self::assertSame($constraints?->startRule(), $plan->startRule());
    }

    /**
     * @return iterable<string, array{string, GenerationPlan<bool>|null, int}>
     */
    public static function providerInputBudgets(): iterable
    {
        yield 'empty' => ['', null, 2];
        yield 'short header' => ["\x01", null, 3];
        yield 'second header byte' => ["\0\x01", null, 258];
        yield 'third header byte' => ["\0\0\x01", null, 551];
        yield 'all header bytes' => ["\x01\x02\x03\x04", null, 4450];
        yield 'body does not change budget' => ["\x01\x02\x03\x04\xff", null, 4450];
        yield 'default maximum' => [pack('V', 4998), null, 5000];
        yield 'default wraparound' => [pack('V', 4999), null, 2];
        yield 'unsigned header' => ["\xff\xff\xff\xff", null, 1462];
        yield 'explicit maximum' => [pack('V', 7), GenerationPlan::all()->withExpansionBudget(9), 9];
        yield 'explicit wraparound' => [pack('V', 8), GenerationPlan::all()->withExpansionBudget(9), 2];
        yield 'single possible budget' => ["\xff", GenerationPlan::fromRule('leaf')->withExpansionBudget(1), 1];
        yield 'largest allowed budget' => [pack('V', 999998), GenerationPlan::all()->withExpansionBudget(1000000), 1000000];
    }

    public function testFromBytesSeparatesProductionChoicesFromLexemesAndCompletesAnOddInput(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('choice'), new NonTerminal('choice')])]),
            'choice' => new ProductionRule('choice', [new Production([new Terminal('T')]), new Production([new Terminal('U')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $lexical->method('resolveSequence')->willReturnCallback(static fn (\SqlFaker\Grammar\Generation\Token\TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) =>
            \Tests\Fixtures\SqlFaker\CoverageFixture::resolve($sequence, $plan, $choose, null, ['T' => ['first', 'second'], 'U' => ['first', 'second']]));
        $builder = new PlanBuilder($grammar, $lexical);
        $plan = GenerationPlan::fromBytes("\0\0\0\0\0\x01\x01\0\0", $builder);

        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('choice', 0));
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('choice', 1));
        self::assertSame('first', $plan->lexemeAt('U', 0));
        self::assertSame('second', $plan->lexemeAt('T', 0));
        self::assertNotNull($plan->candidateKeyAt('U', 0));
    }

    public function testAllCoversTheGrammarWithoutProductionConstraints(): void
    {
        $plan = GenerationPlan::all();

        self::assertNull($plan->startRule());
        self::assertNull($plan->patternAt('statement', 0));
        self::assertSame(PHP_INT_MAX, $plan->maxDepth());
    }

    public function testFromRuleRestrictsTheGenerationRangeWithoutDirectingProductions(): void
    {
        $plan = GenerationPlan::fromRule('select_statement');

        self::assertSame('select_statement', $plan->startRule());
        self::assertNull($plan->patternAt('select_statement', 0));
    }

    public function testConstrainedDirectsEachRuleOccurrence(): void
    {
        $first = ProductionPattern::containing('CONSTRAINT');
        $second = ProductionPattern::containing('FOREIGN', 'KEY');
        $plan = GenerationPlan::constrained('create_table', ['constraint' => [$first, $second]]);

        self::assertSame('create_table', $plan->startRule());
        self::assertSame($first, $plan->patternAt('constraint', 0));
        self::assertSame($second, $plan->patternAt('constraint', 1));
        self::assertNull($plan->patternAt('constraint', 2));
        self::assertNull($plan->patternAt('unknown', 0));
    }

    public function testWithPatternForEveryOccurrenceProducesANewPlanAndActsAsFallback(): void
    {
        $specific = ProductionPattern::exactly();
        $recurring = ProductionPattern::nonEmpty();
        $plan = GenerationPlan::constrained('insert', ['opt_values' => [$specific]]);
        $directed = $plan->withPatternForEveryOccurrence('opt_values', $recurring);

        self::assertNotSame($plan, $directed);
        self::assertNull($plan->patternAt('opt_values', 1));
        self::assertSame($specific, $directed->patternAt('opt_values', 0));
        self::assertSame($recurring, $directed->patternAt('opt_values', 1));
        self::assertSame($recurring, $directed->patternAt('opt_values', 100));
        self::assertNull($directed->patternAt('unknown', 0));
    }

    public function testWithPatternForEveryOccurrenceAccumulatesAcrossRules(): void
    {
        $values = ProductionPattern::nonEmpty();
        $columns = ProductionPattern::containing('IDENT');
        $plan = GenerationPlan::all()
            ->withPatternForEveryOccurrence('opt_values', $values)
            ->withPatternForEveryOccurrence('opt_columns', $columns);

        self::assertSame($values, $plan->patternAt('opt_values', 100));
        self::assertSame($columns, $plan->patternAt('opt_columns', 100));
    }

    public function testRequiringNonEmptyProducesANewPlan(): void
    {
        $plan = GenerationPlan::fromRule('statement');
        $required = $plan->requiringNonEmpty();

        self::assertNotSame($plan, $required);
        self::assertSame('statement', $required->startRule());
    }

    public function testRequiresNonEmptyAnswersWhatThePlanWasBuiltWith(): void
    {
        /** @param GenerationPlan<bool> $plan */
        $requiresNonEmpty = static fn (GenerationPlan $plan): bool => $plan->requiresNonEmpty();

        self::assertFalse($requiresNonEmpty(GenerationPlan::all()));
        self::assertFalse($requiresNonEmpty(GenerationPlan::fromRule('statement')));
        self::assertFalse($requiresNonEmpty(GenerationPlan::constrained('statement', [
            'statement' => [ProductionPattern::nonEmpty()],
        ])));
        self::assertTrue($requiresNonEmpty(GenerationPlan::all()->requiringNonEmpty()));
    }

    public function testWithMaxDepthProducesANewPlanAndNormalizesItsLowerBound(): void
    {
        $plan = GenerationPlan::fromRule('statement');
        $limited = $plan->withMaxDepth(5);
        $minimum = $plan->withMaxDepth(0);

        self::assertNotSame($plan, $limited);
        self::assertSame(PHP_INT_MAX, $plan->maxDepth());
        self::assertSame(5, $limited->maxDepth());
        self::assertSame(1, $minimum->maxDepth());
    }

    public function testWithLexemesDirectsEachTerminalOccurrenceWithoutMutableState(): void
    {
        $plan = GenerationPlan::fromRule('statement')->withLexemes([
            'operator' => ['@@', '?|'],
        ]);

        self::assertSame('@@', $plan->lexemeAt('operator', 0));
        self::assertSame('?|', $plan->lexemeAt('operator', 1));
        self::assertNull($plan->lexemeAt('operator', 2));
        self::assertNull($plan->lexemeAt('unknown', 0));
    }

    public function testLexicalSelectsOneTargetWithParameters(): void
    {
        $plan = GenerationPlan::lexical('quoted_identifier', [
            'minLength' => 2,
            'maxLength' => 8,
        ]);

        self::assertNull($plan->startRule());
        self::assertSame('quoted_identifier', $plan->lexicalTarget());
        self::assertSame(['minLength' => 2, 'maxLength' => 8], $plan->parameters());
    }

    public function testStartRuleAnswersNothingWhenTheWalkBeginsAtTheGrammarEntryPoint(): void
    {
        self::assertNull(GenerationPlan::all()->startRule());
    }

    public function testPatternAtPrefersTheOccurrenceNamedDirectly(): void
    {
        $named = ProductionPattern::containing('CONSTRAINT');
        $fallback = ProductionPattern::nonEmpty();
        $plan = GenerationPlan::constrained('create_table', ['constraint' => [$named]])
            ->withPatternForEveryOccurrence('constraint', $fallback);

        self::assertSame($named, $plan->patternAt('constraint', 0));
        self::assertSame($fallback, $plan->patternAt('constraint', 1));
    }

    public function testLexemeAtAnswersNothingForATerminalThePlanDoesNotDirect(): void
    {
        self::assertNull(GenerationPlan::all()->lexemeAt('IDENT', 0));
    }

    public function testLexicalTargetAnswersNothingWhenTheGrammarIsWalked(): void
    {
        self::assertNull(GenerationPlan::all()->lexicalTarget());
    }

    public function testParametersAnswerNothingWhenTheGrammarIsWalked(): void
    {
        self::assertSame([], GenerationPlan::all()->parameters());
    }

    public function testMaxDepthIsUnboundedUntilTheCallerBoundsIt(): void
    {
        self::assertSame(PHP_INT_MAX, GenerationPlan::all()->maxDepth());
    }
    public function testWithStepBudgetPreservesPolicyAcrossEveryRefinement(): void
    {
        $original = GenerationPlan::fromRule('stmt');
        $bounded = $original->withStepBudget()
            ->requiringNonEmpty()
            ->withLexemes(['TOKEN' => ['literal']])
            ->withMaxDepth(2)
            ->withPatternForEveryOccurrence('stmt', ProductionPattern::exactly('TOKEN'));

        self::assertFalse($original->usesStepBudget());
        self::assertTrue($bounded->usesStepBudget());
        self::assertSame('stmt', $bounded->startRule());
        self::assertSame('literal', $bounded->lexemeAt('TOKEN', 0));
        self::assertSame(2, $bounded->maxDepth());
        self::assertNotNull($bounded->patternAt('stmt', 0));
    }

    public function testUsesStepBudgetDefaultsToFalse(): void
    {
        self::assertFalse(GenerationPlan::all()->usesStepBudget());
        self::assertFalse(GenerationPlan::lexical('identifier', [])->usesStepBudget());
    }

    public function testStatementBoundsTheWalkAtTheRuleItIsGrownFrom(): void
    {
        $plan = GenerationPlan::statement('select_stmt', 12);

        self::assertSame('select_stmt', $plan->startRule());
        self::assertSame(12, $plan->maxDepth());
    }

    public function testStatementWalksTheWholeGrammarWhenNoRuleIsNamed(): void
    {
        self::assertNull(GenerationPlan::statement(null, 12)->startRule());
    }

    public function testWithExpansionBudgetPreservesTheOtherConstraints(): void
    {
        $base = GenerationPlan::all()->withMaxDepth(7);
        $plan = $base->withExpansionBudget(300)->requiringNonEmpty()->withCandidateKeys(['T' => ['candidate']]);
        self::assertNull($base->expansionBudget());
        self::assertSame(300, $plan->expansionBudget());
        self::assertSame(7, $plan->maxDepth());
    }

    /**
     * @return list<array{GenerationPlan<bool>}>
     */
    public static function providerRefinedPlans(): array
    {
        $base = GenerationPlan::constrained('stmt', ['stmt' => [ProductionPattern::at(1)]])
            ->withLexemes(['T' => ['name']])->withCandidateKeys(['T' => ['candidate']]);
        return [[$base->requiringNonEmpty()], [$base->withMaxDepth(7)], [$base->withExpansionBudget(9)],
            [$base->withStepBudget()], [$base->withLexemes(['T' => ['name']])],
            [$base->withPatternForEveryOccurrence('tail', ProductionPattern::exactly())]];
    }

    /**
     * @param GenerationPlan<bool> $plan
     */
    #[DataProvider('providerRefinedPlans')]
    public function testWithCandidateKeysPreservesExplicitInstructionsAcrossRefinements(GenerationPlan $plan): void
    {
        self::assertSame('stmt', $plan->startRule());
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('stmt', 0));
        self::assertSame('name', $plan->lexemeAt('T', 0));
        self::assertSame('candidate', $plan->candidateKeyAt('T', 0));
    }

    public function testCandidateKeyAtDistinguishesPinnedAndUnspecifiedOccurrences(): void
    {
        self::assertNull(GenerationPlan::all()->candidateKeyAt('T', 0));
        $plan = GenerationPlan::all()->withCandidateKeys(['T' => ['one', 'two']]);
        self::assertSame('one', $plan->candidateKeyAt('T', 0));
        self::assertSame('two', $plan->candidateKeyAt('T', 1));
        self::assertNull($plan->candidateKeyAt('T', 2));
        self::assertNull($plan->candidateKeyAt('U', 0));
    }

    public function testExpansionBudgetIsOptionalForExistingPlans(): void
    {
        self::assertNull(GenerationPlan::all()->expansionBudget());
    }

    /**
     * @return list<array{string}>
     */
    public static function providerInputs(): array
    {
        return [[''], ["\x01"], ["\xff\xff\xff\xff"], ["\0\0\0\0abc"], [str_repeat("\xff", 40)]];
    }

    #[DataProvider('providerInputs')]
    public function testFromBytesResolvesChoicesIntoInspectableReusableInstructions(string $input): void
    {
        $grammar = \Tests\Fixtures\SqlFaker\CoverageFixture::syntaxGrammar();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $lexical->method('resolveSequence')->willReturnCallback(\Tests\Fixtures\SqlFaker\CoverageFixture::resolve(...));
        $builder = new PlanBuilder($grammar, $lexical);
        $plan = GenerationPlan::fromBytes($input, $builder);
        self::assertEquals($plan, GenerationPlan::fromBytes($input, $builder));
        self::assertNotNull($plan->patternAt('stmt', 0));
        self::assertNotNull($plan->candidateKeyAt('SELECT', 0) ?? $plan->candidateKeyAt('DELETE', 0));
        self::assertFalse($plan->requiresNonEmpty());
        self::assertNull($plan->startRule());
    }
    public function testHasRemainingPatternsRetainsRecurringConstraintsAfterExplicitOccurrences(): void
    {
        $plan = GenerationPlan::constrained('root', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(1)]]);
        self::assertTrue($plan->hasRemainingPatterns([]));
        self::assertTrue($plan->hasRemainingPatterns(['leaf' => 1]));
        self::assertFalse($plan->hasRemainingPatterns(['leaf' => 2]));
        self::assertTrue($plan->withPatternForEveryOccurrence('leaf', ProductionPattern::at(0))->hasRemainingPatterns(['leaf' => 2]));
        self::assertFalse(GenerationPlan::all()->hasRemainingPatterns([]));
    }

    public function testPatternStateCapsOnlyEquivalentFutureOccurrenceCounters(): void
    {
        $plan = GenerationPlan::constrained('root', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(1)]])
            ->withPatternForEveryOccurrence('other', ProductionPattern::at(0));
        self::assertSame(['leaf' => 0, 'other' => 0], $plan->patternState([]));
        self::assertSame(['leaf' => 1, 'other' => 0], $plan->patternState(['leaf' => 1, 'other' => 8, 'unused' => 4]));
        self::assertSame(['leaf' => 2, 'other' => 0], $plan->patternState(['leaf' => 8]));
    }
}

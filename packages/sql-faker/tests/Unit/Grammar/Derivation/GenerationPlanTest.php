<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionPattern;

#[CoversClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\PlanBuilder::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Production::class)]
#[UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Terminal::class)]
final class GenerationPlanTest extends TestCase
{
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
        $plan = $base->withExpansionBudget(300)->requiringNonEmpty()->withTrivia([' '], ['']);
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
            ->withLexemes(['T' => ['name']])->withTrivia(['/*separator*/'], ['/*optional*/']);
        return [[$base->requiringNonEmpty()], [$base->withMaxDepth(7)], [$base->withExpansionBudget(9)],
            [$base->withStepBudget()], [$base->withLexemes(['T' => ['name']])],
            [$base->withPatternForEveryOccurrence('tail', ProductionPattern::exactly())]];
    }

    /**
     * @param GenerationPlan<bool> $plan
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRefinedPlans')]
    public function testWithTriviaPreservesExplicitInstructionsAcrossRefinements(GenerationPlan $plan): void
    {
        self::assertSame('stmt', $plan->startRule());
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('stmt', 0));
        self::assertSame('name', $plan->lexemeAt('T', 0));
        self::assertSame('/*separator*/', $plan->triviaAt(0, false));
        self::assertSame('/*optional*/', $plan->triviaAt(0, true));
    }

    public function testTriviaAtDistinguishesUnspecifiedFromExplicitEmptyAndDefaults(): void
    {
        self::assertNull(GenerationPlan::all()->triviaAt(0, false));
        $plan = GenerationPlan::all()->withTrivia([' '], ['']);
        self::assertSame('', $plan->triviaAt(0, true));
        self::assertSame('', $plan->triviaAt(50, true));
        self::assertSame(' ', $plan->triviaAt(50, false));
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

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInputs')]
    public function testFromBytesResolvesChoicesIntoInspectableReusableInstructions(string $input): void
    {
        $grammar = \Tests\Fixtures\SqlFaker\CoverageFixture::syntaxGrammar();
        $lexical = $this->createMock(\SqlFaker\Grammar\LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $lexical->method('spellings')->willReturnCallback(static fn (string $terminal): array => [$terminal === '@TRIVIA' ? ' ' : $terminal]);
        $builder = new \SqlFaker\Grammar\Derivation\PlanBuilder($grammar, $lexical);
        $plan = GenerationPlan::fromBytes($input, $builder);
        self::assertEquals($plan, GenerationPlan::fromBytes($input, $builder));
        self::assertNotNull($plan->patternAt('stmt', 0));
        self::assertNotNull($plan->triviaAt(0, false));
        self::assertFalse($plan->requiresNonEmpty());
        self::assertNull($plan->startRule());
    }
}

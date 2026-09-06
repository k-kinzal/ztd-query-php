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

    public function testWithExpansionBudgetPreservesTheLegacyDepthAndOtherPlanConstraints(): void
    {
        $base = GenerationPlan::all()->withMaxDepth(7);
        $plan = $base->withExpansionBudget(300)->requiringNonEmpty()->withChoiceBytes('structure', 'lexical');
        self::assertNull($base->expansionBudget());
        self::assertSame(300, $plan->expansionBudget());
        self::assertSame(7, $plan->maxDepth());
    }

    public function testWithChoiceBytesPreservesIndependentStreamsAcrossPlanRefinements(): void
    {
        $plan = GenerationPlan::all()->withChoiceBytes('s', 'l')->withExpansionBudget(99)->withStepBudget()->withMaxDepth(1);
        self::assertSame('s', $plan->structureBytes());
        self::assertSame('l', $plan->lexicalBytes());
        self::assertSame(99, $plan->expansionBudget());
    }

    public function testExpansionBudgetIsOptionalForExistingPlans(): void
    {
        self::assertNull(GenerationPlan::all()->expansionBudget());
    }

    public function testStructureBytesDistinguishesEmptyInputFromFakerMode(): void
    {
        self::assertNull(GenerationPlan::all()->structureBytes());
        self::assertSame('', GenerationPlan::all()->withChoiceBytes('', '')->structureBytes());
    }

    public function testLexicalBytesAreRetainedWithoutACursorOnThePlan(): void
    {
        self::assertNull(GenerationPlan::all()->lexicalBytes());
        $plan = GenerationPlan::all()->withChoiceBytes('s', 'l');
        self::assertSame('l', $plan->lexicalBytes());
        self::assertSame('l', $plan->lexicalBytes());
    }
    /**
     * @return list<array{GenerationPlan<true>}>
     */
    public static function providerCompletePlans(): array
    {
        $pattern = ProductionPattern::exactly('T');
        $base = GenerationPlan::constrained('stmt', ['stmt' => [$pattern]])
            ->withPatternForEveryOccurrence('stmt', $pattern)->withLexemes(['T' => ['name']])
            ->requiringNonEmpty()->withMaxDepth(7)->withStepBudget()
            ->withExpansionBudget(23)->withChoiceBytes('structure', 'lexical');
        return [[$base->requiringNonEmpty()], [$base->withLexemes(['T' => ['name']])],
            [$base->withMaxDepth(7)], [$base->withStepBudget()], [$base->withExpansionBudget(23)],
            [$base->withChoiceBytes('structure', 'lexical')], [$base->withPatternForEveryOccurrence('stmt', $pattern)]];
    }

    /**
     * @param GenerationPlan<true> $plan
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCompletePlans')]
    public function testEveryRefinementPreservesIndependentGenerationConstraints(GenerationPlan $plan): void
    {
        self::assertSame('stmt', $plan->startRule());
        self::assertEquals(ProductionPattern::exactly('T'), $plan->patternAt('stmt', 0));
        self::assertEquals(ProductionPattern::exactly('T'), $plan->patternAt('stmt', 10));
        self::assertSame('name', $plan->lexemeAt('T', 0));
        self::assertTrue($plan->usesStepBudget());
        self::assertSame(7, $plan->maxDepth());
        self::assertSame(23, $plan->expansionBudget());
        self::assertSame('structure', $plan->structureBytes());
        self::assertSame('lexical', $plan->lexicalBytes());
    }

    /**
     * @return list<array{GenerationPlan<true>}>
     */
    public static function providerLexicalRefinements(): array
    {
        $base = GenerationPlan::lexical('identifier', ['min' => 2, 'max' => 9]);
        return [[$base->requiringNonEmpty()], [$base->withLexemes(['T' => ['name']])],
            [$base->withMaxDepth(7)], [$base->withStepBudget()], [$base->withExpansionBudget(23)],
            [$base->withChoiceBytes('s', 'l')], [$base->withPatternForEveryOccurrence('stmt', ProductionPattern::exactly('T'))]];
    }

    /**
     * @param GenerationPlan<true> $plan
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLexicalRefinements')]
    public function testRefinementsRetainLexicalTargetAndItsParameters(GenerationPlan $plan): void
    {
        self::assertSame('identifier', $plan->lexicalTarget());
        self::assertSame(['min' => 2, 'max' => 9], $plan->parameters());
    }


    /**
     * @return list<array{string, int, int, int, string, string}>
     */
    public static function providerBytePlans(): array
    {
        return [
            ['', 2, 5000, 2, '', ''],
            ["\x01", 2, 5000, 3, '', ''],
            ["\0\x01", 2, 5000, 258, '', ''],
            ["\0\0\x01", 2, 5000, 551, '', ''],
            ["\0\0\0\x01", 2, 5000, 574, '', ''],
            ["\xff\xff\xff\xff", 2, 5000, 1462, '', ''],
            ["\0\0\0\0abcdef", 2, 5000, 2, 'ace', 'bdf'],
            ["\0\0\0\0abc", 2, 5000, 2, 'ac', 'b'],
            ["\xff\xff\xff\xffx", 7, 7, 7, 'x', ''],
            [pack('V', 4998), 2, 5000, 5000, '', ''],
            [pack('V', 4999), 2, 5000, 2, '', ''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerBytePlans')]
    public function testFromBytesAcceptsEveryHeaderAndKeepsChoiceStreamsSeparate(string $input, int $minimum, int $maximum, int $budget, string $structure, string $lexical): void
    {
        $plan = GenerationPlan::fromBytes($input, $minimum, $maximum);
        self::assertSame($budget, $plan->expansionBudget());
        self::assertSame($structure, $plan->structureBytes());
        self::assertSame($lexical, $plan->lexicalBytes());
        self::assertNull($plan->startRule());
        self::assertNull($plan->lexicalTarget());
        self::assertSame(PHP_INT_MAX, $plan->maxDepth());
        self::assertSame([], $plan->parameters());
    }

}

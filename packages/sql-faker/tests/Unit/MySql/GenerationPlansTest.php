<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\ProductionRule;

#[CoversClass(GenerationPlans::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(ProductionRule::class)]
final class GenerationPlansTest extends TestCase
{
    public function testWithoutEmptyRowsPlanConstrainsEveryOptionalValuesOccurrence(): void
    {
        $all = GenerationPlans::withoutEmptyRows();
        $insert = GenerationPlans::withoutEmptyRows('insert_stmt');
        $firstPattern = $all->patternAt('opt_values', 0);
        $laterPattern = $all->patternAt('opt_values', 100);
        $insertPattern = $insert->patternAt('opt_values', 100);

        self::assertNull($all->startRule());
        self::assertSame('insert_stmt', $insert->startRule());
        self::assertNotNull($firstPattern);
        self::assertNotNull($laterPattern);
        self::assertNotNull($insertPattern);
        self::assertFalse($firstPattern->matches([]));
        self::assertTrue($firstPattern->matches(['values']));
        self::assertFalse($laterPattern->matches([]));
        self::assertTrue($insertPattern->matches(['values']));
    }

    public function testMultiTableUpdatePlanRestrictsTheUpdateGrammar(): void
    {
        $plan = GenerationPlans::multiTableUpdateStatement();

        self::assertSame('update_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue(
            $plan->patternAt('table_reference_list', 0)?->matches([
                'table_reference_list',
                ',',
                'table_reference',
            ]) ?? false,
        );
        self::assertTrue($plan->patternAt('table_reference_list', 1)?->matches(['table_reference']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 0)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 1)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 0)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 1)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('opt_use_partition', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('opt_use_partition', 1)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('update_list', 0)?->matches(['update_list', ',', 'update_elem']) ?? false);
        self::assertTrue($plan->patternAt('update_list', 1)?->matches(['update_elem']) ?? false);
    }

    public function testMultiTableDeletePlanRestrictsTheDeleteGrammar(): void
    {
        $plan = GenerationPlans::multiTableDeleteStatement();

        self::assertSame('delete_stmt', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('delete_stmt', 0)?->matches(['table_alias_ref_list', 'table_reference_list']) ?? false,
        );
        self::assertTrue($plan->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue(
            $plan->patternAt('table_alias_ref_list', 0)?->matches([
                'table_alias_ref_list',
                ',',
                'table_ident_opt_wild',
            ]) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('table_alias_ref_list', 1)?->matches(['table_ident_opt_wild']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('table_reference_list', 0)?->matches([
                'table_reference_list',
                ',',
                'table_reference',
            ]) ?? false,
        );
        self::assertTrue($plan->patternAt('table_reference_list', 1)?->matches(['table_reference']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 0)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 1)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 0)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 1)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('opt_use_partition', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('opt_use_partition', 1)?->matches([]) ?? false);
    }

    public function testForeignKeyPlanAdaptsToTheGrammarVersion(): void
    {
        $modernGrammar = new Grammar('table_constraint_def', [
            'table_constraint_def' => new ProductionRule('table_constraint_def', []),
            'opt_constraint_name' => new ProductionRule('opt_constraint_name', []),
        ]);
        $legacyGrammar = new Grammar('key_def', []);

        $modern = GenerationPlans::foreignKeyConstraint($modernGrammar);
        $legacy = GenerationPlans::foreignKeyConstraint($legacyGrammar);

        self::assertSame('table_constraint_def', $modern->startRule());
        self::assertTrue(
            $modern->patternAt('table_constraint_def', 0)?->matches(['FOREIGN', 'KEY_SYM']) ?? false,
        );
        self::assertTrue($modern->patternAt('opt_constraint_name', 0)?->matches(['CONSTRAINT']) ?? false);
        self::assertTrue($modern->patternAt('opt_ident', 0)?->matches(['ident']) ?? false);
        self::assertTrue($modern->patternAt('opt_ref_list', 0)?->matches(['reference']) ?? false);
        self::assertSame('key_def', $legacy->startRule());
        self::assertTrue($legacy->patternAt('key_def', 0)?->matches(['FOREIGN', 'KEY_SYM']) ?? false);
        self::assertTrue($legacy->patternAt('opt_constraint', 0)?->matches(['constraint']) ?? false);
        self::assertTrue($legacy->patternAt('opt_ident', 0)?->matches(['ident']) ?? false);
        self::assertTrue($legacy->patternAt('opt_ref_list', 0)?->matches(['reference']) ?? false);
    }
}

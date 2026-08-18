<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\PostgreSql\GenerationPlans;

#[CoversClass(GenerationPlans::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class GenerationPlansTest extends TestCase
{
    public function testForeignKeyPlanRestrictsTheTableConstraintGrammar(): void
    {
        $plan = GenerationPlans::foreignKeyConstraint();

        self::assertSame('TableConstraint', $plan->startRule());
        self::assertTrue($plan->patternAt('TableConstraint', 0)?->matches(['CONSTRAINT']) ?? false);
        self::assertTrue($plan->patternAt('ConstraintElem', 0)?->matches(['FOREIGN', 'KEY']) ?? false);
        self::assertTrue($plan->patternAt('opt_column_list', 0)?->matches(['column']) ?? false);
    }

    public function testFunctionUpsertPlanRestrictsTheConflictFunctionGrammar(): void
    {
        $plan = GenerationPlans::insertFunctionUpsertStatement();

        self::assertSame('InsertStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('insert_target', 0)?->matches(['qualified_name']) ?? false);
        self::assertTrue($plan->patternAt('qualified_name', 0)?->matches(['ColId']) ?? false);
        self::assertTrue(
            $plan->patternAt('insert_rest', 0)?->matches(['insert_column_list', 'SelectStmt']) ?? false,
        );
        self::assertTrue($plan->patternAt('insert_column_list', 0)?->matches(['insert_column_item']) ?? false);
        self::assertTrue($plan->patternAt('select_no_parens', 0)?->matches(['simple_select']) ?? false);
        self::assertTrue($plan->patternAt('simple_select', 0)?->matches(['values_clause']) ?? false);
        self::assertTrue(
            $plan->patternAt('values_clause', 0)?->matches(['VALUES', '(', 'expr_list', ')']) ?? false,
        );
        self::assertTrue($plan->patternAt('expr_list', 0)?->matches(['a_expr']) ?? false);
        self::assertTrue($plan->patternAt('opt_on_conflict', 0)?->matches(['DO', 'UPDATE']) ?? false);
        self::assertTrue($plan->patternAt('opt_conf_expr', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('opt_indirection', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('opt_indirection', 1)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('a_expr', 0)?->matches(['c_expr']) ?? false);
        self::assertTrue($plan->patternAt('a_expr', 1)?->matches(['c_expr']) ?? false);
        self::assertTrue($plan->patternAt('c_expr', 0)?->matches(['AexprConst']) ?? false);
        self::assertTrue($plan->patternAt('c_expr', 1)?->matches(['func_expr']) ?? false);
        self::assertTrue($plan->patternAt('AexprConst', 0)?->matches(['Iconst']) ?? false);
        self::assertTrue($plan->patternAt('func_expr', 0)?->matches(['func_application']) ?? false);
    }

    public function testTemporaryTablePlanRequiresTheTemporaryProduction(): void
    {
        $plan = GenerationPlans::temporaryTableStatement();

        self::assertSame('CreateStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('OptTemp', 0)?->matches(['TEMP']) ?? false);
    }

    public function testViewPlanStartsFromTheViewGrammar(): void
    {
        self::assertSame('ViewStmt', GenerationPlans::viewStatement()->startRule());
    }

    public function testGeneratedColumnPlanRequiresTheGeneratedConstraint(): void
    {
        $plan = GenerationPlans::generatedColumnStatement();

        self::assertSame('CreateStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('CreateStmt', 0)?->matches(['OptTableElementList']) ?? false);
        self::assertTrue($plan->patternAt('OptTableElementList', 0)?->matches(['TableElement']) ?? false);
        self::assertTrue($plan->patternAt('TableElement', 0)?->matches(['columnDef']) ?? false);
        self::assertTrue(
            $plan->patternAt('ColQualList', 0)?->matches(['ColQualList', 'ColConstraint']) ?? false,
        );
        self::assertTrue($plan->patternAt('ColQualList', 1)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('ColConstraint', 0)?->matches(['ColConstraintElem']) ?? false);
        self::assertTrue($plan->patternAt('ColConstraintElem', 0)?->matches(['GENERATED', 'STORED']) ?? false);
    }

    public function testForeignKeyCascadePlanRequiresBothCascadeActions(): void
    {
        $plan = GenerationPlans::foreignKeyCascadeStatement();

        self::assertSame('CreateStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('CreateStmt', 0)?->matches(['OptTableElementList']) ?? false);
        self::assertTrue($plan->patternAt('OptTableElementList', 0)?->matches(['TableElement']) ?? false);
        self::assertTrue($plan->patternAt('TableElement', 0)?->matches(['TableConstraint']) ?? false);
        self::assertTrue(
            $plan->patternAt('ConstraintElem', 0)?->matches(['FOREIGN', 'KEY', 'REFERENCES']) ?? false,
        );
        self::assertTrue($plan->patternAt('key_actions', 0)?->matches(['key_update', 'key_delete']) ?? false);
        self::assertTrue($plan->patternAt('key_action', 0)?->matches(['CASCADE']) ?? false);
        self::assertTrue($plan->patternAt('key_action', 1)?->matches(['CASCADE']) ?? false);
    }

    public function testChildPartitionPlanRequiresARangeBound(): void
    {
        $plan = GenerationPlans::partitionOfStatement();

        self::assertSame('CreateStmt', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('CreateStmt', 0)?->matches(['PARTITION', 'OF', 'PartitionBoundSpec']) ?? false,
        );
        self::assertTrue($plan->patternAt('PartitionBoundSpec', 0)?->matches(['FROM', 'TO']) ?? false);
    }
}

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
}

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
    public function testForeignKeyConstraintRestrictsTheTableConstraintGrammar(): void
    {
        $plan = GenerationPlans::foreignKeyConstraint();

        self::assertSame('TableConstraint', $plan->startRule());
        self::assertTrue($plan->patternAt('TableConstraint', 0)?->matches(['CONSTRAINT']) ?? false);
        self::assertTrue($plan->patternAt('ConstraintElem', 0)?->matches(['FOREIGN', 'KEY']) ?? false);
        self::assertTrue($plan->patternAt('opt_column_list', 0)?->matches(['column']) ?? false);
    }

    public function testInsertFunctionUpsertStatementRestrictsTheConflictFunctionGrammar(): void
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

    public function testTemporaryTableStatementRequiresTheTemporaryProduction(): void
    {
        $plan = GenerationPlans::temporaryTableStatement();

        self::assertSame('CreateStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('OptTemp', 0)?->matches(['TEMP']) ?? false);
    }

    public function testViewStatementStartsFromTheViewGrammar(): void
    {
        self::assertSame('ViewStmt', GenerationPlans::viewStatement()->startRule());
    }

    public function testGeneratedColumnStatementRequiresTheGeneratedConstraint(): void
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

    public function testForeignKeyCascadeStatementRequiresBothCascadeActions(): void
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

    public function testPartitionOfStatementRequiresARangeBound(): void
    {
        $plan = GenerationPlans::partitionOfStatement();

        self::assertSame('CreateStmt', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('CreateStmt', 0)?->matches(['PARTITION', 'OF', 'PartitionBoundSpec']) ?? false,
        );
        self::assertTrue($plan->patternAt('PartitionBoundSpec', 0)?->matches(['FROM', 'TO']) ?? false);
    }

    public function testTableSampleStatementRequiresASamplingClause(): void
    {
        $plan = GenerationPlans::tableSampleStatement();

        self::assertSame('SelectStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('SelectStmt', 0)?->matches(['select_no_parens']) ?? false);
        self::assertTrue($plan->patternAt('select_no_parens', 0)?->matches(['simple_select']) ?? false);
        self::assertTrue(
            $plan->patternAt('simple_select', 0)?->matches(['SELECT', 'opt_target_list', 'from_clause']) ?? false,
        );
        self::assertTrue($plan->patternAt('from_clause', 0)?->matches(['table_ref']) ?? false);
        self::assertTrue(
            $plan->patternAt('table_ref', 0)?->matches(['relation_expr', 'tablesample_clause']) ?? false,
        );
    }

    public function testDoStatementRequiresASingleStringBlock(): void
    {
        $plan = GenerationPlans::doStatement();

        self::assertSame('DoStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('dostmt_opt_list', 0)?->matches(['dostmt_opt_item']) ?? false);
        self::assertTrue($plan->patternAt('dostmt_opt_item', 0)?->matches(['Sconst']) ?? false);
    }

    public function testMergeStatementRequiresAllMutationBranches(): void
    {
        $plan = GenerationPlans::mergeStatement();

        self::assertSame('MergeStmt', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('merge_when_list', 0)?->matches(['merge_when_list', 'merge_when_clause']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('merge_when_list', 1)?->matches(['merge_when_list', 'merge_when_clause']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('merge_when_list', 2)?->matches(['merge_when_list', 'merge_when_clause']) ?? false,
        );
        self::assertTrue($plan->patternAt('merge_when_list', 3)?->matches(['merge_when_clause']) ?? false);
        self::assertTrue(
            $plan->patternAt('merge_when_clause', 0)?->matches(['merge_when_tgt_matched', 'merge_delete']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('merge_when_clause', 1)?->matches(['merge_when_tgt_matched', 'DO', 'NOTHING']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('merge_when_clause', 2)?->matches(['merge_when_tgt_matched', 'merge_update']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('merge_when_clause', 3)?->matches(['merge_when_tgt_not_matched', 'merge_insert']) ?? false,
        );
    }

    public function testCopyStatementStartsFromTheCopyGrammar(): void
    {
        $plan = GenerationPlans::copyStatement();

        self::assertSame('CopyStmt', $plan->startRule());
    }

    public function testPartialIndexUpsertStatementRequiresAConflictPredicate(): void
    {
        $plan = GenerationPlans::partialIndexUpsertStatement();

        self::assertSame('InsertStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('qualified_name', 0)?->matches(['ColId']) ?? false);
        self::assertTrue($plan->patternAt('insert_rest', 0)?->matches(['DEFAULT', 'VALUES']) ?? false);
        self::assertTrue($plan->patternAt('opt_on_conflict', 0)?->matches(['DO', 'UPDATE']) ?? false);
        self::assertTrue(
            $plan->patternAt('opt_conf_expr', 0)?->matches(['index_params', 'where_clause']) ?? false,
        );
        self::assertTrue($plan->patternAt('where_clause', 0)?->matches(['a_expr']) ?? false);
    }

    public function testDomainDmlStatementsCoverAllMutationGrammars(): void
    {
        $plans = GenerationPlans::domainDmlStatements();

        self::assertSame(
            ['InsertStmt', 'UpdateStmt', 'DeleteStmt'],
            array_map(static fn (GenerationPlan $plan): ?string => $plan->startRule(), $plans),
        );
        self::assertTrue($plans[0]->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plans[1]->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plans[2]->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
    }

    public function testFullTextSearchStatementRequiresTheMatchOperator(): void
    {
        $plan = GenerationPlans::fullTextSearchStatement();

        self::assertSame('SelectStmt', $plan->startRule());
        self::assertTrue($plan->patternAt('SelectStmt', 0)?->matches(['select_no_parens']) ?? false);
        self::assertTrue($plan->patternAt('select_no_parens', 0)?->matches(['simple_select']) ?? false);
        self::assertTrue(
            $plan->patternAt('simple_select', 0)?->matches([
                'SELECT',
                'opt_target_list',
                'from_clause',
                'where_clause',
            ]) ?? false,
        );
        self::assertTrue($plan->patternAt('opt_target_list', 0)?->matches(['target_list']) ?? false);
        self::assertTrue($plan->patternAt('target_list', 0)?->matches(['target_el']) ?? false);
        self::assertTrue($plan->patternAt('target_el', 0)?->matches(['*']) ?? false);
        self::assertTrue($plan->patternAt('into_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('from_clause', 0)?->matches(['from_list']) ?? false);
        self::assertTrue($plan->patternAt('from_list', 0)?->matches(['table_ref']) ?? false);
        self::assertTrue(
            $plan->patternAt('table_ref', 0)?->matches(['relation_expr', 'opt_alias_clause']) ?? false,
        );
        self::assertTrue($plan->patternAt('relation_expr', 0)?->matches(['qualified_name']) ?? false);
        self::assertTrue($plan->patternAt('qualified_name', 0)?->matches(['ColId']) ?? false);
        self::assertTrue($plan->patternAt('opt_alias_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('where_clause', 0)?->matches(['a_expr']) ?? false);
        self::assertTrue($plan->patternAt('group_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('having_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('window_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('a_expr', 0)?->matches(['a_expr', 'qual_Op', 'a_expr']) ?? false);
        self::assertTrue($plan->patternAt('a_expr', 1)?->matches(['c_expr']) ?? false);
        self::assertTrue($plan->patternAt('a_expr', 2)?->matches(['c_expr']) ?? false);
        self::assertTrue($plan->patternAt('qual_Op', 0)?->matches(['Op']) ?? false);
        self::assertTrue($plan->patternAt('c_expr', 0)?->matches(['columnref']) ?? false);
        self::assertTrue($plan->patternAt('c_expr', 1)?->matches(['columnref']) ?? false);
        self::assertTrue($plan->patternAt('columnref', 0)?->matches(['ColId']) ?? false);
        self::assertTrue($plan->patternAt('columnref', 1)?->matches(['ColId']) ?? false);
        self::assertTrue($plan->patternAt('ColId', 0)?->matches(['IDENT']) ?? false);
        self::assertTrue($plan->patternAt('ColId', 1)?->matches(['IDENT']) ?? false);
        self::assertTrue($plan->patternAt('ColId', 2)?->matches(['IDENT']) ?? false);
        self::assertSame('@@', $plan->lexemeAt('Op', 0));
    }

    public function testQuotedIdentifierPlansThatLexeme(): void
    {
        $plan = GenerationPlans::quotedIdentifier(1, 2);

        self::assertSame('quoted_identifier', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testStringLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::stringLiteral(1, 2);

        self::assertSame('string_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testIntegerLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::integerLiteral(1, 2);

        self::assertSame('integer_literal', $plan->lexicalTarget());
        self::assertSame(['min' => 1, 'max' => 2], $plan->parameters());
    }

    public function testDecimalLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::decimalLiteral(1, 2);

        self::assertSame('decimal_literal', $plan->lexicalTarget());
        self::assertSame(['precision' => 1, 'scale' => 2], $plan->parameters());
    }

    public function testFloatLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::floatLiteral(1, 2, 3, 4);

        self::assertSame('float_literal', $plan->lexicalTarget());
        self::assertSame(['precision' => 1, 'scale' => 2, 'minExponent' => 3, 'maxExponent' => 4], $plan->parameters());
    }

    public function testHexLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::hexLiteral(1, 2);

        self::assertSame('hex_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testBinaryLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::binaryLiteral(1, 2);

        self::assertSame('binary_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testDollarQuotedStringPlansThatLexeme(): void
    {
        $plan = GenerationPlans::dollarQuotedString(1, 2);

        self::assertSame('dollar_quoted_string', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testParameterMarkerPlansThatLexeme(): void
    {
        $plan = GenerationPlans::parameterMarker(1, 2);

        self::assertSame('parameter_marker', $plan->lexicalTarget());
        self::assertSame(['min' => 1, 'max' => 2], $plan->parameters());
    }
}

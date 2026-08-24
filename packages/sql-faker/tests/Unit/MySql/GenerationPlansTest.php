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
    public function testWithoutEmptyRowsConstrainsEveryOptionalValuesOccurrence(): void
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

    public function testMultiTableUpdateStatementRestrictsTheUpdateGrammar(): void
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

    public function testMultiTableDeleteStatementRestrictsTheDeleteGrammar(): void
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

    public function testForeignKeyConstraintAdaptsToTheGrammarVersion(): void
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

    public function testUpdateJoinDerivedStatementRestrictsTheDerivedTableGrammar(): void
    {
        $plan = GenerationPlans::updateJoinDerivedStatement();

        self::assertSame('update_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('opt_with_clause', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 0)?->matches(['joined_table']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 1)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 2)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('table_reference', 3)?->matches(['table_factor']) ?? false);
        self::assertTrue($plan->patternAt('joined_table', 0)?->matches(['ON_SYM']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 0)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 1)?->matches(['derived_table']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 2)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('opt_from_clause', 0)?->matches(['table_reference_list']) ?? false);
        self::assertTrue($plan->patternAt('opt_group_clause', 0)?->matches(['GROUP_SYM']) ?? false);
    }

    public function testInsertSelectCompoundStatementRestrictsTheUnionGrammar(): void
    {
        $plan = GenerationPlans::insertSelectCompoundStatement();

        self::assertSame('insert_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('insert_stmt', 0)?->matches(['insert_query_expression']) ?? false);
        self::assertTrue($plan->patternAt('query_expression_body', 0)?->matches(['UNION_SYM']) ?? false);
        self::assertTrue($plan->patternAt('query_expression_body', 1)?->matches(['query_primary']) ?? false);
        self::assertTrue($plan->patternAt('query_expression_body', 2)?->matches(['query_primary']) ?? false);
        self::assertTrue($plan->patternAt('query_primary', 0)?->matches(['query_specification']) ?? false);
        self::assertTrue($plan->patternAt('union_option', 0)?->matches(['ALL']) ?? false);
    }

    public function testInsertRowAliasUpsertStatementRestrictsTheValuesGrammar(): void
    {
        $plan = GenerationPlans::insertRowAliasUpsertStatement();

        self::assertSame('insert_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('insert_stmt', 0)?->matches(['insert_from_constructor']) ?? false);
        self::assertTrue(
            $plan->patternAt('insert_from_constructor', 0)?->matches(['insert_values']) ?? false,
        );
        self::assertTrue($plan->patternAt('value_or_values', 0)?->matches(['VALUES']) ?? false);
        self::assertTrue($plan->patternAt('opt_values_reference', 0)?->matches(['alias']) ?? false);
        self::assertTrue($plan->patternAt('opt_insert_update_list', 0)?->matches(['update']) ?? false);
    }

    public function testInsertFunctionUpsertStatementRestrictsTheConflictFunctionGrammar(): void
    {
        $plan = GenerationPlans::insertFunctionUpsertStatement();

        self::assertSame('insert_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('insert_stmt', 0)?->matches(['insert_from_constructor']) ?? false);
        self::assertTrue(
            $plan->patternAt('insert_from_constructor', 0)?->matches(['insert_values']) ?? false,
        );
        self::assertTrue($plan->patternAt('values_list', 0)?->matches(['row_value']) ?? false);
        self::assertTrue($plan->patternAt('opt_values', 0)?->matches(['values']) ?? false);
        self::assertTrue($plan->patternAt('values', 0)?->matches(['expr_or_default']) ?? false);
        self::assertTrue($plan->patternAt('expr_or_default', 0)?->matches(['DEFAULT_SYM']) ?? false);
        self::assertTrue($plan->patternAt('expr_or_default', 1)?->matches(['expr']) ?? false);
        self::assertTrue($plan->patternAt('opt_insert_update_list', 0)?->matches(['update']) ?? false);
        self::assertTrue($plan->patternAt('simple_expr', 0)?->matches(['function_call_conflict']) ?? false);
        self::assertTrue($plan->patternAt('function_call_conflict', 0)?->matches(['IF']) ?? false);
    }

    public function testTemporaryTableStatementRequiresTheTemporaryProduction(): void
    {
        $plan = GenerationPlans::temporaryTableStatement();

        self::assertSame('create_table_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('opt_temporary', 0)?->matches(['TEMPORARY']) ?? false);
    }

    public function testViewStatementRestrictsTheCreateGrammar(): void
    {
        $plan = GenerationPlans::viewStatement();

        self::assertSame('create', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('create', 0)?->matches(['CREATE', 'view_or_trigger_or_sp_or_event']) ?? false,
        );
        self::assertTrue(
            $plan->patternAt('view_or_trigger_or_sp_or_event', 0)?->matches([
                'no_definer',
                'init_lex_create_info',
                'no_definer_tail',
            ]) ?? false,
        );
        self::assertTrue($plan->patternAt('no_definer_tail', 0)?->matches(['view_tail']) ?? false);
        self::assertTrue($plan->patternAt('query_primary', 0)?->matches(['query_specification']) ?? false);
    }

    public function testGeneratedColumnStatementRequiresTheGeneratedAttribute(): void
    {
        $plan = GenerationPlans::generatedColumnStatement();

        self::assertSame('create_table_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('create_table_stmt', 0)?->matches(['table_element_list']) ?? false);
        self::assertTrue($plan->patternAt('table_element', 0)?->matches(['column_def']) ?? false);
        self::assertTrue($plan->patternAt('field_def', 0)?->matches(['opt_generated_always', 'expr']) ?? false);
        self::assertTrue($plan->patternAt('opt_generated_always', 0)?->matches(['GENERATED']) ?? false);
        self::assertTrue($plan->patternAt('opt_stored_attribute', 0)?->matches(['STORED_SYM']) ?? false);
    }

    public function testForeignKeyCascadeStatementRequiresBothCascadeActions(): void
    {
        $plan = GenerationPlans::foreignKeyCascadeStatement();

        self::assertSame('create_table_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('create_table_stmt', 0)?->matches(['table_element_list']) ?? false);
        self::assertTrue($plan->patternAt('table_element', 0)?->matches(['table_constraint_def']) ?? false);
        self::assertTrue(
            $plan->patternAt('table_constraint_def', 0)?->matches(['FOREIGN', 'KEY_SYM', 'references']) ?? false,
        );
        self::assertTrue($plan->patternAt('opt_ref_list', 0)?->matches(['reference']) ?? false);
        self::assertTrue(
            $plan->patternAt('opt_on_update_delete', 0)?->matches(['UPDATE_SYM', 'DELETE_SYM']) ?? false,
        );
        self::assertTrue($plan->patternAt('delete_option', 0)?->matches(['CASCADE']) ?? false);
        self::assertTrue($plan->patternAt('delete_option', 1)?->matches(['CASCADE']) ?? false);
    }

    public function testPartitionSelectStatementRequiresThePartitionClause(): void
    {
        $plan = GenerationPlans::partitionSelectStatement();

        self::assertSame('select_stmt', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('query_expression', 0)?->matches([
                'query_expression_body',
                'opt_order_clause',
                'opt_limit_clause',
            ]) ?? false,
        );
        self::assertTrue($plan->patternAt('query_expression_body', 0)?->matches(['query_primary']) ?? false);
        self::assertTrue($plan->patternAt('query_primary', 0)?->matches(['query_specification']) ?? false);
        self::assertTrue($plan->patternAt('opt_from_clause', 0)?->matches(['from_tables']) ?? false);
        self::assertTrue($plan->patternAt('from_tables', 0)?->matches(['table_reference_list']) ?? false);
        self::assertTrue($plan->patternAt('table_factor', 0)?->matches(['single_table']) ?? false);
        self::assertTrue($plan->patternAt('opt_use_partition', 0)?->matches(['PARTITION_SYM']) ?? false);
    }

    public function testLoadDataStatementStartsFromTheLoadGrammar(): void
    {
        $plan = GenerationPlans::loadDataStatement();

        self::assertSame('load_stmt', $plan->startRule());
    }

    public function testFullTextSearchStatementRequiresMatchAgainstGrammar(): void
    {
        $plan = GenerationPlans::fullTextSearchStatement();

        self::assertSame('select_stmt', $plan->startRule());
        self::assertTrue($plan->patternAt('query_primary', 0)?->matches(['query_specification']) ?? false);
        self::assertTrue($plan->patternAt('select_item_list', 0)?->matches(['*']) ?? false);
        self::assertTrue($plan->patternAt('opt_from_clause', 0)?->matches(['from_tables']) ?? false);
        self::assertTrue($plan->patternAt('from_tables', 0)?->matches(['table_reference_list']) ?? false);
        self::assertTrue($plan->patternAt('opt_where_clause', 0)?->matches(['expr']) ?? false);
        self::assertTrue($plan->patternAt('simple_expr', 0)?->matches(['MATCH', 'AGAINST']) ?? false);
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

    public function testNationalStringLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::nationalStringLiteral(1, 2);

        self::assertSame('national_string_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testDollarQuotedStringPlansThatLexeme(): void
    {
        $plan = GenerationPlans::dollarQuotedString(1, 2);

        self::assertSame('dollar_quoted_string', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testIntegerLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::integerLiteral(1, 2);

        self::assertSame('integer_literal', $plan->lexicalTarget());
        self::assertSame(['min' => 1, 'max' => 2], $plan->parameters());
    }

    public function testLongIntegerLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::longIntegerLiteral(1, 2);

        self::assertSame('long_integer_literal', $plan->lexicalTarget());
        self::assertSame(['min' => 1, 'max' => 2], $plan->parameters());
    }

    public function testUnsignedBigIntLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::unsignedBigIntLiteral(1, 2);

        self::assertSame('unsigned_big_int_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
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

    public function testQuotedHexLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::quotedHexLiteral(1, 2);

        self::assertSame('quoted_hex_literal', $plan->lexicalTarget());
        self::assertSame(['minBytes' => 1, 'maxBytes' => 2], $plan->parameters());
    }

    public function testBinaryLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::binaryLiteral(1, 2);

        self::assertSame('binary_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testHostnamePlansThatLexeme(): void
    {
        $plan = GenerationPlans::hostname(1, 2, 3);

        self::assertSame('hostname', $plan->lexicalTarget());
        self::assertSame(['minParts' => 1, 'maxParts' => 2, 'maxPartLength' => 3], $plan->parameters());
    }
}

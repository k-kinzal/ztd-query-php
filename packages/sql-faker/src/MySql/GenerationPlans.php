<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\MySql\Grammar\Grammar;

/**
 * Names the generation plans this dialect's provider is built from.
 *
 * A plan is how a caller says which SQL it wants, and the ones a provider
 * offers are a fixed vocabulary rather than something each caller assembles.
 * Naming them here keeps that vocabulary in one place and lets the provider
 * read as a list of what it can generate.
 */
final class GenerationPlans
{
    /**
     * Directs a walk that never leaves a VALUES list with nothing in it.
     *
     * @param non-empty-string|null $startRule Rule the walk begins at, or null for the grammar entry point
     *
     * @return GenerationPlan<true> Plan whose row lists always carry a row
     */
    public static function withoutEmptyRows(?string $startRule = null): GenerationPlan
    {
        $plan = $startRule === null
            ? GenerationPlan::all()
            : GenerationPlan::fromRule($startRule);

        return $plan
            ->withPatternForEveryOccurrence('opt_values', ProductionPattern::nonEmpty())
            ->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function quotedIdentifier(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('quoted_identifier', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function stringLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('string_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function nationalStringLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('national_string_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function dollarQuotedString(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('dollar_quoted_string', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function integerLiteral(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('integer_literal', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function longIntegerLiteral(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('long_integer_literal', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function unsignedBigIntLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('unsigned_big_int_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function decimalLiteral(int $precision, int $scale): GenerationPlan
    {
        return GenerationPlan::lexical('decimal_literal', compact('precision', 'scale'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function floatLiteral(
        int $precision,
        int $scale,
        int $minExponent,
        int $maxExponent,
    ): GenerationPlan {
        return GenerationPlan::lexical(
            'float_literal',
            compact('precision', 'scale', 'minExponent', 'maxExponent'),
        );
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function hexLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('hex_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function quotedHexLiteral(int $minBytes, int $maxBytes): GenerationPlan
    {
        return GenerationPlan::lexical('quoted_hex_literal', compact('minBytes', 'maxBytes'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function binaryLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('binary_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function hostname(int $minParts, int $maxParts, int $maxPartLength): GenerationPlan
    {
        return GenerationPlan::lexical('hostname', compact('minParts', 'maxParts', 'maxPartLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function foreignKeyConstraint(Grammar $grammar): GenerationPlan
    {
        $startRule = isset($grammar->ruleMap['table_constraint_def'])
            ? 'table_constraint_def'
            : 'key_def';
        $constraintNameRule = isset($grammar->ruleMap['opt_constraint_name'])
            ? 'opt_constraint_name'
            : 'opt_constraint';
        $constraintNameSymbol = $constraintNameRule === 'opt_constraint_name'
            ? 'CONSTRAINT'
            : 'constraint';

        return GenerationPlan::constrained($startRule, [
            $startRule => [ProductionPattern::containing('FOREIGN', 'KEY_SYM')],
            $constraintNameRule => [ProductionPattern::containing($constraintNameSymbol)],
            'opt_ident' => [ProductionPattern::nonEmpty()],
            'opt_ref_list' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function multiTableUpdateStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('update_stmt', [
            'opt_with_clause' => [ProductionPattern::exactly()],
            'table_reference_list' => [
                ProductionPattern::exactly('table_reference_list', ',', 'table_reference'),
                ProductionPattern::exactly('table_reference'),
            ],
            'table_reference' => [
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
            ],
            'table_factor' => [
                ProductionPattern::exactly('single_table'),
                ProductionPattern::exactly('single_table'),
            ],
            'opt_use_partition' => [
                ProductionPattern::exactly(),
                ProductionPattern::exactly(),
            ],
            'update_list' => [
                ProductionPattern::exactly('update_list', ',', 'update_elem'),
                ProductionPattern::exactly('update_elem'),
            ],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function updateJoinDerivedStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('update_stmt', [
            'opt_with_clause' => [ProductionPattern::exactly()],
            'table_reference' => [
                ProductionPattern::exactly('joined_table'),
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
            ],
            'joined_table' => [ProductionPattern::containing('ON_SYM')],
            'table_factor' => [
                ProductionPattern::exactly('single_table'),
                ProductionPattern::exactly('derived_table'),
                ProductionPattern::exactly('single_table'),
            ],
            'opt_from_clause' => [ProductionPattern::nonEmpty()],
            'opt_group_clause' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function insertSelectCompoundStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('insert_stmt', [
            'insert_stmt' => [ProductionPattern::containing('insert_query_expression')],
            'query_expression_body' => [
                ProductionPattern::containing('UNION_SYM'),
                ProductionPattern::exactly('query_primary'),
                ProductionPattern::exactly('query_primary'),
            ],
            'query_primary' => [
                ProductionPattern::exactly('query_specification'),
            ],
            'union_option' => [ProductionPattern::exactly('ALL')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function insertRowAliasUpsertStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('insert_stmt', [
            'insert_stmt' => [ProductionPattern::containing('insert_from_constructor')],
            'insert_from_constructor' => [ProductionPattern::containing('insert_values')],
            'value_or_values' => [ProductionPattern::exactly('VALUES')],
            'opt_values_reference' => [ProductionPattern::nonEmpty()],
            'opt_insert_update_list' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function insertFunctionUpsertStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('insert_stmt', [
            'insert_stmt' => [ProductionPattern::containing('insert_from_constructor')],
            'insert_from_constructor' => [ProductionPattern::containing('insert_values')],
            'values_list' => [ProductionPattern::exactly('row_value')],
            'opt_values' => [ProductionPattern::exactly('values')],
            'values' => [ProductionPattern::exactly('expr_or_default')],
            'expr_or_default' => [
                ProductionPattern::exactly('DEFAULT_SYM'),
                ProductionPattern::exactly('expr'),
            ],
            'opt_insert_update_list' => [ProductionPattern::nonEmpty()],
            'simple_expr' => [ProductionPattern::exactly('function_call_conflict')],
            'function_call_conflict' => [ProductionPattern::containing('IF')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function temporaryTableStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('create_table_stmt', [
            'opt_temporary' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function viewStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('create', [
            'create' => [ProductionPattern::exactly('CREATE', 'view_or_trigger_or_sp_or_event')],
            'view_or_trigger_or_sp_or_event' => [
                ProductionPattern::exactly('no_definer', 'init_lex_create_info', 'no_definer_tail'),
            ],
            'no_definer_tail' => [ProductionPattern::exactly('view_tail')],
            'query_primary' => [ProductionPattern::exactly('query_specification')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function generatedColumnStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('create_table_stmt', [
            'create_table_stmt' => [ProductionPattern::containing('table_element_list')],
            'table_element' => [ProductionPattern::exactly('column_def')],
            'field_def' => [ProductionPattern::containing('opt_generated_always', 'expr')],
            'opt_generated_always' => [ProductionPattern::nonEmpty()],
            'opt_stored_attribute' => [ProductionPattern::containing('STORED_SYM')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function foreignKeyCascadeStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('create_table_stmt', [
            'create_table_stmt' => [ProductionPattern::containing('table_element_list')],
            'table_element' => [ProductionPattern::exactly('table_constraint_def')],
            'table_constraint_def' => [ProductionPattern::containing('FOREIGN', 'KEY_SYM', 'references')],
            'opt_ref_list' => [ProductionPattern::nonEmpty()],
            'opt_on_update_delete' => [
                ProductionPattern::containing('UPDATE_SYM', 'DELETE_SYM'),
            ],
            'delete_option' => [
                ProductionPattern::exactly('CASCADE'),
                ProductionPattern::exactly('CASCADE'),
            ],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function partitionSelectStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('select_stmt', [
            'query_expression' => [
                ProductionPattern::exactly('query_expression_body', 'opt_order_clause', 'opt_limit_clause'),
            ],
            'query_expression_body' => [ProductionPattern::exactly('query_primary')],
            'query_primary' => [ProductionPattern::exactly('query_specification')],
            'opt_from_clause' => [ProductionPattern::nonEmpty()],
            'from_tables' => [ProductionPattern::exactly('table_reference_list')],
            'table_factor' => [ProductionPattern::exactly('single_table')],
            'opt_use_partition' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function loadDataStatement(): GenerationPlan
    {
        return GenerationPlan::fromRule('load_stmt')->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function fullTextSearchStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('select_stmt', [
            'query_primary' => [ProductionPattern::exactly('query_specification')],
            'select_item_list' => [ProductionPattern::exactly('*')],
            'opt_from_clause' => [ProductionPattern::nonEmpty()],
            'from_tables' => [ProductionPattern::exactly('table_reference_list')],
            'opt_where_clause' => [ProductionPattern::nonEmpty()],
            'simple_expr' => [ProductionPattern::containing('MATCH', 'AGAINST')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function multiTableDeleteStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('delete_stmt', [
            'delete_stmt' => [ProductionPattern::containing('table_alias_ref_list', 'table_reference_list')],
            'opt_with_clause' => [ProductionPattern::exactly()],
            'table_alias_ref_list' => [
                ProductionPattern::exactly('table_alias_ref_list', ',', 'table_ident_opt_wild'),
                ProductionPattern::exactly('table_ident_opt_wild'),
            ],
            'table_reference_list' => [
                ProductionPattern::exactly('table_reference_list', ',', 'table_reference'),
                ProductionPattern::exactly('table_reference'),
            ],
            'table_reference' => [
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
            ],
            'table_factor' => [
                ProductionPattern::exactly('single_table'),
                ProductionPattern::exactly('single_table'),
            ],
            'opt_use_partition' => [
                ProductionPattern::exactly(),
                ProductionPattern::exactly(),
            ],
        ])->requiringNonEmpty();
    }

    /**
     * Directs a bounded walk that must produce a statement.
     *
     * Every generator method on the provider makes the same promise: the SQL
     * it answers is a statement, not the empty string a nullable rule may
     * otherwise reduce to, and it stops before the caller's depth.
     *
     * @param non-empty-string|null $startRule Rule the statement is grown from, or null for the grammar entry point
     * @param int $maxDepth How deep the walk may recurse
     *
     * @return GenerationPlan<true> Plan for one bounded, non-empty statement
     */
    public static function statement(?string $startRule, int $maxDepth): GenerationPlan
    {
        $plan = $startRule === null
            ? GenerationPlan::all()
            : GenerationPlan::fromRule($startRule);

        return $plan->requiringNonEmpty()->withMaxDepth($maxDepth);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Modern;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Statement\Relation;

/**
 * Lowers the table factors of the 8.0 and later grammars.
 *
 * Rule: MYSQL-FACTOR-MODERN-001. Scope: table_factor (8.0 and later),
 * single_table_parens, joined_table_parens, table_reference_list_parens,
 * derived_table, table_function. Parentheses around references are nested
 * relations, one pair each; the parentheses a derived table requires belong
 * to it. Constructs: TableReference (through the table parts),
 * NestedRelation, TableList, DerivedTable, JsonTable. Terminates: recursion
 * follows strictly smaller parts; the lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html,
 * https://dev.mysql.com/doc/refman/8.4/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class FactorRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a table factor.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function factor(Form $form): Relation
    {
        return match ($form->signature) {
            'table_factor: single_table' => (new TableRule($this->lowering))->table($form->node(0)),
            'table_factor: derived_table' => $this->derived($form->node(0)),
            'table_factor: table_function' => $this->function($form->node(0)),
            'table_factor: single_table_parens', 'table_factor: joined_table_parens', 'table_factor: table_reference_list_parens' => $this->parens($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers table references in parentheses.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function parens(Node $parens): NestedRelation
    {
        $form = $this->lowering->form($parens);

        return new NestedRelation(match ($form->signature) {
            'single_table_parens: ( single_table_parens )', 'joined_table_parens: ( joined_table_parens )', 'table_reference_list_parens: ( table_reference_list_parens )' => $this->parens($form->node(1)),
            'single_table_parens: ( single_table )' => (new TableRule($this->lowering))->table($form->node(1)),
            'joined_table_parens: ( joined_table )' => (new ReferenceRule($this->lowering))->joined($form->node(1)),
            'table_reference_list_parens: ( table_reference_list , table_reference )' => new TableList([...(new FromRule($this->lowering))->members($form->node(1)), (new ReferenceRule($this->lowering))->reference($form->node(3))]),
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Lowers a derived table.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function derived(Node $derived): DerivedTable
    {
        $form = $this->lowering->form($derived);
        $lateral = match ($form->signature) {
            'derived_table: table_subquery opt_table_alias opt_derived_column_list' => false,
            'derived_table: LATERAL_SYM table_subquery opt_table_alias opt_derived_column_list' => true,
            default => throw ImplementationGap::production($form),
        };
        $at = $lateral ? 1 : 0;
        $tables = new TableRule($this->lowering);

        return new DerivedTable((new ExpressionRule($this->lowering))->subquery($form->node($at)), $tables->alias($form->node($at + 1)), $tables->columns($form->node($at + 2)), $lateral, $tables->mark($form->node($at + 1)));
    }

    /**
     * Lowers a JSON_TABLE table function.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function function(Node $function): JsonTable
    {
        $form = $this->lowering->form($function);
        if ($form->signature !== 'table_function: JSON_TABLE_SYM ( expr , text_literal columns_clause ) opt_table_alias') {
            throw ImplementationGap::production($form);
        }

        return new JsonTable(
            $this->lowering->expressions->expression($form->node(2)),
            $this->lowering->literals->string($form->node(4)),
            $this->lowering->calls->jsonTableColumns($form->node(5)),
            (new TableRule($this->lowering))->alias($form->node(7)),
            (new TableRule($this->lowering))->mark($form->node(7)),
        );
    }
}

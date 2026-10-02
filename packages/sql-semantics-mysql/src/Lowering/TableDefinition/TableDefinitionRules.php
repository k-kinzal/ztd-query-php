<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the table definition family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-TABLE-DEFINITION-ENTRY-001. Scope: CREATE TABLE with columns, keys, constraints and options, CREATE
 * INDEX, and views.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableDefinitionRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a definition statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL table definition family: statement');
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this
     * family.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function definition(Form $form): Statement
    {
        throw ImplementationGap::rule('MySQL table definition family: definition');
    }

    /**
     * Lowers CREATE VIEW: a node of `view_tail`, the node of `view_replace_or_algorithm` when one is
     * written, and the definer.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function createView(Node $tail, ?Node $prefix, ?Account $definer): Statement
    {
        throw ImplementationGap::rule('MySQL table definition family: createView');
    }

    /**
     * Lowers one column, index or constraint: a node of `column_def`, `key_def`, `table_constraint_def`,
     * `field_spec` or `table_element`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function tableElement(Node $element): TableElement
    {
        throw ImplementationGap::rule('MySQL table definition family: tableElement');
    }

    /**
     * Lowers a parenthesized element list: a node of `create_field_list` or `table_element_list`.
     *
     * @return list<TableElement>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function tableElements(Node $list): array
    {
        throw ImplementationGap::rule('MySQL table definition family: tableElements');
    }

    /**
     * Lowers a column definition without its name: a node of `field_def` with the node of `opt_references`
     * when one follows, or the nodes of `type` and `opt_attribute` (5.6).
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function columnSpecification(Node $definition, ?Node $second = null): ColumnSpecification
    {
        throw ImplementationGap::rule('MySQL table definition family: columnSpecification');
    }

    /**
     * Lowers table options: a node of `create_table_options_space_separated`.
     *
     * @return list<TableOption>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function tableOptions(Node $options): array
    {
        throw ImplementationGap::rule('MySQL table definition family: tableOptions');
    }

    /**
     * Tells whether an index or column is declared VISIBLE: a node of `visibility`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function visible(Node $visibility): bool
    {
        throw ImplementationGap::rule('MySQL table definition family: visible');
    }

    /**
     * Tells whether a check constraint is declared ENFORCED: a node of `constraint_enforcement`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function enforced(Node $enforcement): bool
    {
        throw ImplementationGap::rule('MySQL table definition family: enforced');
    }
}

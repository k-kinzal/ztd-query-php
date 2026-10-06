<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column\ColumnRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyOptionRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\ReferenceRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\View\ViewRule;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the table definition family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-TABLE-DEFINITION-ENTRY-001. Scope: CREATE TABLE with columns, keys, constraints and options, CREATE
 * INDEX, views, and the 5.7 statement PARSE_GCOL_EXPR. The method names, parameters and return types are fixed by
 * the family plan; each method delegates to the rule classes of this family.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 * Status: Implemented.
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
     * Lowers a definition statement: a node of `create_table_stmt`, `create_index_stmt`, `alter_view_stmt`,
     * `drop_view_stmt` or `parse_gcol_expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Statement
    {
        return match ($statement->name) {
            'create_table_stmt' => (new CreateTableRule($this->lowering))->modern($statement),
            'create_index_stmt' => (new IndexRule($this->lowering))->statement($statement),
            'alter_view_stmt' => (new ViewRule($this->lowering))->alter($this->lowering->form($statement)),
            'drop_view_stmt' => (new ViewRule($this->lowering))->drop($this->lowering->form($statement)),
            'parse_gcol_expr' => (new ColumnRule($this->lowering))->parse($statement),
            default => throw ImplementationGap::production($this->lowering->form($statement)),
        };
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` that MYSQL-DEFINITION-ROUTES-001 routes to this
     * family.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function definition(Form $form): Statement
    {
        $view = new ViewRule($this->lowering);

        return match (strstr($form->signature, ':', true)) {
            'create' => str_contains($form->signature, ' INDEX_SYM ') ? (new IndexRule($this->lowering))->index($form) : (new CreateTableRule($this->lowering))->legacy($form),
            'alter' => $view->alter($form),
            'drop' => $view->drop($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CREATE VIEW: a node of `view_tail`, the node of `view_replace_or_algorithm` when one is
     * written, and the definer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function createView(Node $tail, ?Node $prefix, ?Account $definer): Statement
    {
        return (new ViewRule($this->lowering))->create($tail, $prefix, $definer);
    }

    /**
     * Lowers one column, index or constraint: a node of `column_def`, `key_def`, `table_constraint_def`,
     * `field_spec` or `table_element`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function tableElement(Node $element): TableElement
    {
        return (new ElementRule($this->lowering))->element($element);
    }

    /**
     * Lowers a parenthesized element list: a node of `create_field_list` or `table_element_list`.
     *
     * @return list<TableElement>
     * @throws ImplementationGap When a production has no rule
     */
    public function tableElements(Node $list): array
    {
        return (new ElementRule($this->lowering))->elements($list);
    }

    /**
     * Lowers a column definition without its name: a node of `field_def` with the node of `opt_references`
     * when one follows, or the nodes of `type` and `opt_attribute` (5.6).
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function columnSpecification(Node $definition, ?Node $second = null): ColumnSpecification
    {
        $rule = new ColumnRule($this->lowering);
        if ($definition->name === 'type') {
            return $rule->specification($definition, $second);
        }

        return $rule->specification($definition, null, $second === null ? null : (new ReferenceRule($this->lowering))->optional($second));
    }

    /**
     * Lowers table options: a node of `create_table_options_space_separated`, `create_table_options` or
     * `opt_create_table_options`.
     *
     * @return list<TableOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function tableOptions(Node $options): array
    {
        return (new TableOptionRule($this->lowering))->options($options);
    }

    /**
     * Tells whether an index or column is declared VISIBLE: a node of `visibility`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function visible(Node $visibility): bool
    {
        return (new KeyOptionRule($this->lowering))->visible($visibility);
    }

    /**
     * Tells whether a check constraint is declared ENFORCED: a node of `constraint_enforcement`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function enforced(Node $enforcement): bool
    {
        return (new KeyRule($this->lowering))->enforced($enforcement);
    }
}

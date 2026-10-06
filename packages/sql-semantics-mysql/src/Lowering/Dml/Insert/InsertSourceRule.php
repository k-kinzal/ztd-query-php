<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml\Insert;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Dml\ListRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Statement\Query;

/**
 * Lowers what INSERT and REPLACE write: the column list and the rows of VALUES or the query.
 *
 * Rule: MYSQL-INSERT-SOURCE-LOWERING-001. Scope: insert_field_spec,
 * insert_from_constructor, insert_from_subquery, insert_query_expression,
 * insert_values, values_list, no_braces, row_value, value_or_values, fields,
 * insert_ident, insert_columns, insert_column. A column list is
 * ColumnList, also when it is empty; rows are InsertedRow; a query source
 * is lowered by the query family, the 5.x forms through legacyQuery and
 * legacyParenthesizedQuery. The answer is the column list with either the
 * rows or the query. Terminates: lists are flattened by MYSQL-DML-LIST-001;
 * the parts are strict subtrees. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/5.7/en/insert.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class InsertSourceRule
{
    /**
     * The productions that write a column list before their source, by the position of the list.
     */
    private const LISTED = [
        'insert_field_spec: insert_values' => null, 'insert_field_spec: ( ) insert_values' => -1, 'insert_field_spec: ( fields ) insert_values' => 1,
        'insert_from_constructor: insert_values' => null, 'insert_from_constructor: ( ) insert_values' => -1,
        'insert_from_constructor: ( fields ) insert_values' => 1, 'insert_from_constructor: ( insert_columns ) insert_values' => 1,
        'insert_from_subquery: insert_query_expression' => null, 'insert_from_subquery: ( ) insert_query_expression' => -1,
        'insert_from_subquery: ( fields ) insert_query_expression' => 1,
        'insert_query_expression: query_expression_with_opt_locking_clauses' => null,
        'insert_query_expression: ( ) query_expression_with_opt_locking_clauses' => -1,
        'insert_query_expression: ( insert_columns ) query_expression_with_opt_locking_clauses' => 1,
    ];

    /**
     * The productions of the column lists.
     */
    private const COLUMN_LISTS = ['fields: fields , insert_ident', 'fields: insert_ident', 'insert_columns: insert_columns , insert_column', 'insert_columns: insert_column'];

    /**
     * The productions of the row lists.
     */
    private const ROW_LISTS = ['values_list: values_list , no_braces', 'values_list: no_braces', 'values_list: values_list , row_value', 'values_list: row_value'];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a source with its optional column list: a node of `insert_field_spec`, `insert_from_constructor`, `insert_from_subquery` or `insert_query_expression`.
     *
     * @return array{ColumnList|null, list<InsertedRow>|Query}
     * @throws ImplementationGap When a production has no rule
     */
    public function source(Node $source): array
    {
        $form = $this->lowering->form($source);
        if (!array_key_exists($form->signature, self::LISTED)) {
            return [null, $this->body($source)];
        }
        $position = self::LISTED[$form->signature];
        $columns = $position === null ? null : new ColumnList($position === -1 ? [] : $this->columns($form->node($position)));
        $body = $form->node->children[count($form->node->children) - 1];
        if (!$body instanceof Node) {
            throw ImplementationGap::production($form);
        }
        if ($body->name === 'query_expression_with_opt_locking_clauses') {
            return [$columns, $this->lowering->queries->query($body)];
        }
        $inner = $this->source($body);
        if ($inner[0] !== null) {
            throw ImplementationGap::production($form);
        }

        return [$columns, $inner[1]];
    }

    /**
     * Lowers rows or a query without a column list: a node of `insert_values` or `insert_query_expression` (5.7).
     *
     * @return list<InsertedRow>|Query
     * @throws ImplementationGap When a production has no rule
     */
    public function body(Node $body): array|Query
    {
        $form = $this->lowering->form($body);

        return match ($form->signature) {
            'insert_values: VALUES values_list', 'insert_values: VALUE_SYM values_list' => $this->rows($form->node(1)),
            'insert_values: value_or_values values_list' => $this->valued($form->node(0), $form->node(1)),
            'insert_values: create_select union_clause', 'insert_query_expression: create_select opt_union_clause' => $this->lowering->queries->legacyQuery($form->node(0), $form->node(1)),
            'insert_values: ( create_select ) union_opt', 'insert_query_expression: ( create_select ) union_opt' => $this->lowering->queries->legacyParenthesizedQuery($form->node(1), $form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the rows after the keyword VALUE or VALUES: a node of `value_or_values` and a node of `values_list`.
     *
     * @return list<InsertedRow>
     * @throws ImplementationGap When a production has no rule
     */
    public function valued(Node $keyword, Node $list): array
    {
        $form = $this->lowering->form($keyword);
        if ($form->signature !== 'value_or_values: VALUE_SYM' && $form->signature !== 'value_or_values: VALUES') {
            throw ImplementationGap::production($form);
        }

        return $this->rows($list);
    }

    /**
     * Lowers the rows of a node of `values_list`.
     *
     * @return list<InsertedRow>
     * @throws ImplementationGap When a production has no rule
     */
    public function rows(Node $list): array
    {
        $rows = [];
        foreach ((new ListRule($this->lowering))->items($list, self::ROW_LISTS) as $item) {
            $row = $this->lowering->form($item);
            if ($row->signature !== 'no_braces: ( opt_values )' && $row->signature !== 'row_value: ( opt_values )') {
                throw ImplementationGap::production($row);
            }
            $rows[] = new InsertedRow($this->lowering->dml->rowValues($row->node(1)));
        }

        return $rows;
    }

    /**
     * Lowers the columns of a node of `fields` or `insert_columns`.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $columns = [];
        foreach ((new ListRule($this->lowering))->items($list, self::COLUMN_LISTS) as $item) {
            $column = $this->lowering->form($item);
            $columns[] = match ($column->signature) {
                'insert_ident: simple_ident_nospvar', 'insert_column: simple_ident_nospvar' => $this->lowering->names->column($column->node(0)),
                'insert_ident: table_wild' => $this->lowering->names->wildcard($column->node(0)),
                default => throw ImplementationGap::production($column),
            };
        }

        return $columns;
    }
}

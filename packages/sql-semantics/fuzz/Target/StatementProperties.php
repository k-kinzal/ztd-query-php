<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\ExpressionKind;

/**
 * Checks semantic obligations imposed by the parsed operation, including omitted lowering.
 */
final class StatementProperties
{
    /**
     * Requires meaningful effects rather than a command kind with an untouched syntax tree.
     *
     * @throws RuntimeException
     */
    public function verify(BoundStatement $statement): void
    {
        if (in_array($statement->kind, ['SET', 'RESET', 'PRAGMA'], true) && $statement->settings === []) {
            throw new RuntimeException('A configuration statement lost its named effects.');
        }
        if (in_array($statement->kind, ['INSERT', 'REPLACE'], true)) {
            $this->insertion($statement);
        }
        if ($statement->kind === 'MERGE' && ($statement->merge === null || count($statement->merge->actions) !== count(Tree::outer($statement->source, ['merge_when_clause'])))) {
            throw new RuntimeException('MERGE matching or ordered branches were omitted.');
        }
        if ($statement->kind === 'UPDATE' && $statement->writes === []) {
            throw new RuntimeException('An UPDATE lost its assignment destinations.');
        }
        if ($statement->setOperator === null && in_array($statement->kind, ['SELECT', 'UPDATE', 'DELETE'], true)) {
            $where = QueryNodes::local($statement->source, ['where_clause', 'opt_where_clause', 'where_or_current_clause', 'where_opt', 'where_opt_ret'])[0] ?? null;
            if ($where !== null && Tree::outer($where, ['a_expr', 'expr', 'cursor_name']) !== [] && $statement->where === null) {
                throw new RuntimeException('A row predicate was omitted from the semantic graph.');
            }
        }
    }

    /**
     * Checks ordered input destinations independently of the existence of rows or outputs.
     *
     * @throws RuntimeException
     */
    public function insertion(BoundStatement $statement): void
    {
        $input = $statement->insertion;
        if ($input === null) {
            throw new RuntimeException('An INSERT lost its input-to-column mapping.');
        }
        if ($input->explicitColumns) {
            $list = QueryNodes::local($statement->source, ['insert_column_list', 'insert_columns', 'fields', 'idlist_opt'])[0] ?? null;
            $names = $list === null ? [] : Tree::outer($list, ['insert_column_item', 'insert_column', 'insert_ident', 'nm']);
            if (count($input->columns) !== count($names)) {
                throw new RuntimeException('An INSERT destination was omitted.');
            }
        }
        foreach ($input->columns as $column) {
            if ($column->kind === ExpressionKind::UnresolvedColumn && $column->reference === []) {
                throw new RuntimeException('An INSERT destination lost its unresolved name.');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Statement\StatementBinder;

/**
 * Locates the WITH clause owned by one operation, excluding its query inputs.
 * @visibility SqlSemantics
 */
final class CteNodes
{
    /**
     * Returns the statement's declaration boundary, when present.
     */
    public static function clause(Node $source): ?Node
    {
        $query = in_array(StatementBinder::operation($source), ['WITH', 'SELECT', 'VALUES', 'TABLE', '('], true);
        return self::find($source, $query);
    }

    /**
     * Walks only wrappers belonging to the current operation.
     */
    public static function find(Node $source, bool $query): ?Node
    {
        if (in_array($source->name, ['with_clause', 'wqlist'], true)) {
            return $source;
        }
        if (QueryNodes::isBody($source)) {
            return null;
        }
        foreach ($source->children as $child) {
            if (!$child instanceof Node || in_array($child->name, ['a_expr', 'expr', 'common_table_expr', 'wqitem', 'table_subquery', 'from_clause', 'from', 'returning_clause'], true)) {
                continue;
            }
            if (!$query && in_array($child->name, ['SelectStmt', 'select_stmt', 'select', 'query_expression', 'select_with_parens', 'subquery', 'insert_query_expression', 'create_select'], true)) {
                continue;
            }
            $clause = self::find($child, $query);
            if ($clause !== null) {
                return $clause;
            }
        }
        return null;
    }
}

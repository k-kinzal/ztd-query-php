<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\InsertReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\InsertRules;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;

/**
 * Lowers MySQL insertion destinations and distinct source forms.
 * @visibility SqlSemantics
 */
final class SemanticInsert implements InsertRules
{
    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node $statement, InsertReader $reader): InsertRows|InsertSelect
    {
        $insert = Tree::outer($statement, ['insert_stmt', 'insert'])[0] ?? null;
        if ($insert === null) {
            Tree::unsupported($statement, 'INSERT form');
        }
        Tree::assertChildren($insert, ['opt_INTO', 'table_ident', 'insert_from_constructor', 'insert_query_expression', 'insert_from_subquery', 'insert2', 'insert_field_spec'], ['INSERT']);
        foreach (['insert2', 'insert_table', 'table_name_with_opt_use_partition'] as $wrapper) {
            foreach ($insert->find($wrapper) as $node) {
                Tree::assertChildren($node, ['insert_table', 'table_name_with_opt_use_partition', 'table_ident'], ['INTO']);
            }
        }
        $table = Tree::outer($insert, ['table_ident'])[0] ?? null;
        $body = Tree::child($insert, ['insert_from_constructor', 'insert_query_expression', 'insert_from_subquery', 'insert_field_spec']);
        if ($table === null || $body === null) {
            Tree::unsupported($insert, 'INSERT body');
        }
        Tree::assertChildren($body, ['insert_columns', 'fields', 'insert_values', 'query_expression_with_opt_locking_clauses', 'insert_query_expression'], ['(', ')']);
        $columns = Tree::child($body, ['insert_columns', 'fields']);
        $target = $reader->target($table, $columns === null ? [] : Tree::outer($columns, ['insert_column', 'insert_ident']));
        $query = Tree::outer($body, ['query_expression_with_opt_locking_clauses', 'create_select'])[0] ?? null;
        if ($query !== null) {
            return $reader->select($target, $body);
        }
        return $reader->values($target, Tree::outer($body, ['row_value', 'no_braces']));
    }
}

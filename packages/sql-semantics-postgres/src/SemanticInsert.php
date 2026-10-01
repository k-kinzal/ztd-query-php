<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\InsertReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\InsertRules;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;

/**
 * Lowers PostgreSQL insertion destinations and distinct source forms.
 * @visibility SqlSemantics
 */
final class SemanticInsert implements InsertRules
{
    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node $statement, InsertReader $reader): InsertRows|InsertSelect
    {
        $insert = Tree::outer($statement, ['InsertStmt'])[0] ?? null;
        if ($insert === null) {
            Tree::unsupported($statement, 'INSERT form');
        }
        Tree::assertChildren($insert, ['insert_target', 'insert_rest'], ['INSERT', 'INTO']);
        $destination = Tree::child($insert, ['insert_target']);
        $body = Tree::child($insert, ['insert_rest']);
        if ($destination === null || $body === null) {
            Tree::unsupported($insert, 'INSERT body');
        }
        Tree::assertChildren($destination, ['qualified_name'], []);
        Tree::assertChildren($body, ['insert_column_list', 'SelectStmt'], ['(', ')']);
        $table = Tree::child($destination, ['qualified_name']);
        $query = Tree::child($body, ['SelectStmt']);
        if ($table === null || $query === null) {
            Tree::unsupported($body, 'INSERT source');
        }
        $columns = Tree::child($body, ['insert_column_list']);
        $target = $reader->target($table, $columns === null ? [] : Tree::outer($columns, ['insert_column_item']));
        $values = Tree::outer($query, ['values_clause']);
        if ($values !== []) {
            foreach (Tree::outer($query, ['select_no_parens']) as $wrapper) {
                Tree::assertChildren($wrapper, ['simple_select'], []);
            }
            return $reader->values($target, Tree::outer($values[0], ['expr_list']));
        }
        return $reader->select($target, $query);
    }
}

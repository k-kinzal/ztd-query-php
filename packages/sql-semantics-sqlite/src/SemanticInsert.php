<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\InsertReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\InsertRules;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;

/**
 * Lowers SQLite insertion destinations and distinct source forms.
 * @visibility SqlSemantics
 */
final class SemanticInsert implements InsertRules
{
    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node $statement, InsertReader $reader): InsertRows|InsertSelect
    {
        Tree::assertChildren($statement, ['insert_cmd', 'xfullname', 'idlist_opt', 'select'], ['INTO']);
        $command = Tree::child($statement, ['insert_cmd']);
        $table = Tree::child($statement, ['xfullname']);
        $query = Tree::child($statement, ['select']);
        if ($command === null || $table === null || $query === null || Tree::text($command) !== 'INSERT') {
            Tree::unsupported($statement, 'INSERT form');
        }
        $columns = Tree::child($statement, ['idlist_opt']);
        $target = $reader->target($table, $columns === null ? [] : Tree::outer($columns, ['nm']));
        $values = Tree::outer($query, ['mvalues', 'values']);
        if ($values !== []) {
            Tree::assertChildren($query, ['selectnowith'], []);
            foreach ($query->find('oneselect') as $select) {
                Tree::assertChildren($select, ['mvalues', 'values'], []);
            }
            return $reader->values($target, Tree::outer($values[0], ['nexprlist']));
        }
        return $reader->select($target, $query);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\TableUse;

/**
 * Finds a relation name written twice in one namespace, as the server does while reading.
 *
 * Rule: MYSQL-RELATION-NAMES-001. In a FROM clause, a table reference is named
 * by its alias, or by its table name when it has none, within the database it
 * reads (the current database when none is written); a derived table and a
 * JSON table are named by their alias and belong to no database. In a table
 * list (DROP, maintenance, FLUSH, LOCK, key-cache statements) a table is named
 * by its alias or table name within its database. Two spellings that are
 * equal byte for byte name one relation twice; the server refuses the
 * statement while reading it, even inside a stored program body
 * (ER_NONUNIQ_TABLE; verified on 8.4.7). Spellings that differ only by letter
 * case, or a written database equal to the current one, depend on the
 * server's name comparison and remain a contextual condition
 * (MYSQL-FROM-SCOPE-001). Terminates: one pass over the finite relation trees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RelationNames
{
    /**
     * Answers the first relation of the trees whose name an earlier relation of the trees already takes.
     *
     * @param list<Relation|Dual|null> $relations The FROM clause trees, in written order
     */
    public function repeated(array $relations): ?NonUniqueTable
    {
        $seen = [];
        foreach ($this->visible($relations) as [$key, $name, $node]) {
            if (isset($seen[$key])) {
                return new NonUniqueTable($name, $node);
            }
            $seen[$key] = true;
        }

        return null;
    }

    /**
     * Answers the first table of a list whose name an earlier table of the list already takes.
     *
     * @param list<array{QualifiedName, Name|null, TableUse|null}> $tables Each table name, its alias and its node, in written order
     */
    public function repeatedTables(array $tables): ?NonUniqueTable
    {
        $seen = [];
        foreach ($tables as [$name, $alias, $node]) {
            $key = ($name->schema->value ?? "\0") . "\0" . ($alias ?? $name->name)->value;
            if (isset($seen[$key])) {
                return new NonUniqueTable($alias ?? $name->name, $node);
            }
            $seen[$key] = true;
        }

        return null;
    }

    /**
     * Lists the relations the trees make visible with the key of each: its database, or none for a derived or JSON table, and its name.
     *
     * @param list<Relation|Dual|null> $relations
     * @return list<array{string, Name, TableUse|null}>
     */
    public function visible(array $relations): array
    {
        $visible = [];
        foreach ($relations as $relation) {
            if ($relation instanceof TableList) {
                array_push($visible, ...$this->visible($relation->members));
            } elseif ($relation instanceof JoinedTable) {
                array_push($visible, ...$this->visible([$relation->left, $relation->right]));
            } elseif ($relation instanceof NestedRelation || $relation instanceof OdbcJoin || $relation instanceof EscapedRelation) {
                array_push($visible, ...$this->visible([$relation->relation]));
            } elseif ($relation instanceof NamedRelation) {
                $name = $relation->alias() ?? $relation->name()->name;
                $visible[] = [($relation->name()->schema->value ?? "\0") . "\0" . $name->value, $name, $relation];
            } elseif ($relation instanceof DerivedTable || $relation instanceof JsonTable) {
                $visible[] = ["\0" . $relation->alias->value, $relation->alias, $relation];
            }
        }

        return $visible;
    }
}

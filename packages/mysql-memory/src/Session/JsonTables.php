<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSyntax;
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Relation;

/**
 * Finds a JSON_TABLE of the outermost table references of a statement whose path is not valid.
 *
 * The server reads the paths of a JSON_TABLE when it sets up the tables of the query block that
 * names it, after it opens the tables of the statement and before it resolves any name of the
 * block, so a path that is not valid is refused before an unknown column and before a target of
 * UPDATE that is not updatable (verified on a live 8.4 server). The row path is read first, then
 * the paths of the columns in written order.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility MySqlMemory
 */
final class JsonTables
{
    /**
     * Answers the error of the first path that is not valid of a JSON_TABLE the statement names among its outermost table references, if any.
     */
    public function first(Node $statement): ?SqlError
    {
        $tables = match (true) {
            $statement instanceof Update, $statement instanceof MultipleDelete => $statement->tables,
            default => array_filter([(new Placement())->first($statement)?->from]),
        };
        foreach ($tables as $table) {
            foreach ($this->functions($table) as $function) {
                foreach ([$function->path->value(), ...$this->paths($function->columns)] as $path) {
                    try {
                        JsonPath::parse($path);
                    } catch (JsonSyntax $failure) {
                        return new SqlError(DataError::InvalidJsonPath, DataError::InvalidJsonPath->message($failure->position), $failure);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Answers the JSON_TABLE calls of table references in written order, outside derived tables.
     *
     * @return list<JsonTable>
     */
    public function functions(Relation $relation): array
    {
        $parts = match (true) {
            $relation instanceof JsonTable => [],
            $relation instanceof JoinedTable => [$relation->left, $relation->right],
            $relation instanceof TableList => $relation->members,
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => [$relation->relation],
            default => [],
        };
        $found = $relation instanceof JsonTable ? [$relation] : [];
        foreach ($parts as $part) {
            array_push($found, ...$this->functions($part));
        }

        return $found;
    }

    /**
     * Answers the paths of JSON_TABLE columns in written order, those of nested columns included.
     *
     * @param list<JsonTableColumn> $columns
     * @return list<string>
     */
    public function paths(array $columns): array
    {
        $paths = [];
        foreach ($columns as $column) {
            if ($column instanceof PathColumn) {
                $paths[] = $column->path->value();
            } elseif ($column instanceof NestedColumns) {
                array_push($paths, $column->path->value(), ...$this->paths($column->columns));
            }
        }

        return $paths;
    }
}

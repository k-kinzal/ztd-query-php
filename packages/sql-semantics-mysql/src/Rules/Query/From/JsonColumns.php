<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the single flattened output namespace of a JSON_TABLE declaration.
 * Nested paths add columns to that same namespace; they do not open a new one.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JsonColumns
{
    /**
     * Reports the first repeated output, retaining the earlier spelling.
     *
     * @param list<JsonTableColumn> $columns The complete column declarations
     */
    public function problem(array $columns): ?DuplicateColumn
    {
        $seen = [];
        foreach ($this->names($columns) as $name) {
            $key = mb_strtolower($name->value, 'UTF-8');
            if (isset($seen[$key])) {
                return new DuplicateColumn($seen[$key]);
            }
            $seen[$key] = $name;
        }
        return null;
    }

    /**
     * Flattens every nested path in column order.
     *
     * @param list<JsonTableColumn> $columns The complete column declarations
     * @return list<Name> Declared output names
     */
    public function names(array $columns): array
    {
        $names = [];
        foreach ($columns as $column) {
            if ($column instanceof NestedColumns) {
                array_push($names, ...$this->names($column->columns));
            } elseif ($column instanceof OrdinalityColumn || $column instanceof PathColumn) {
                $names[] = $column->name;
            }
        }
        return $names;
    }
    /**
     * Checks only input occurrences reached through the statement's lexical scopes.
     */
    public function of(\SqlSemantics\Statement\Node $root): ?DuplicateColumn
    {
        return $this->inputs([$root]);
    }

    /**
     * Checks a write's input sources before choosing its ordinary or retained role.
     *
     * @param list<\SqlSemantics\Statement\Node> $inputs Complete input occurrences
     */
    public function inputs(array $inputs, ?\SqlSemantics\Platform\MySql\Statement\Query\WithClause $with = null): ?DuplicateColumn
    {
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->inputs($inputs, $with instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\With ? $with : null) as $node) {
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable) {
                $problem = $this->problem($node->columns);
                if ($problem !== null) {
                    return $problem;
                }
            }
        }
        return null;
    }

}

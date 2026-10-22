<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Visits read queries with the structural outputs of their lexical common tables.
 * Only reached definitions participate; an unused definition remains a template.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryScopes
{
    /**
     * @return list<array{Query, array<string, list<Name|null>|null>}> Queries and enclosing structural bindings
     */
    public function of(Node $root): array
    {
        $reached = array_fill_keys(array_map(static fn (Node $node): int => spl_object_id($node), (new ReadDependencies())->nodes($root)), true);
        return $this->walk($root, [], $reached);
    }

    /**
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     * @param array<int, true> $reached Definitions reached through lexical references
     * @return list<array{Query, array<string, list<Name|null>|null>}> Queries with their original scope
     */
    public function walk(Node $node, array $common, array $reached): array
    {
        $found = $node instanceof Query ? [[$node, $common]] : [];
        if ($node instanceof M\QueryExpression && $node->with instanceof M\With\With) {
            foreach ($node->with->tables as $table) {
                $scope = $common;
                if ($node->with->recursive) {
                    $scope[$table->name->value] = null;
                }
                $columns = $table->columns !== [] ? $table->columns : (new QueryColumns())->of($table->query, common: $scope);
                if ($node->with->recursive) {
                    $scope[$table->name->value] = $columns;
                }
                if (isset($reached[spl_object_id($table)])) {
                    array_push($found, ...$this->walk($table->query, $scope, $reached));
                }
                $common[$table->name->value] = $columns;
            }
        }
        foreach ((new ReadDependencies())->children($node) as $child) {
            array_push($found, ...$this->walk($child, $common, $reached));
        }
        return $found;
    }
}

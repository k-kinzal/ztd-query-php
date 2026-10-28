<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Node;

/**
 * Follows lexical CTE dependencies without consulting a catalog or execution state.
 *
 * A definition sees earlier definitions and its enclosing scope; RECURSIVE also
 * exposes its own name. Only references reached from the body activate a CTE.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReadDependencies
{
    /**
     * @param bool $childrenFirst Whether completed operand dependencies precede their owners
     * @return list<Node> Nodes used by the read, including transitively referenced definitions
     */
    public function nodes(Node $root, Comparison $names = Comparison::Sensitive, bool $childrenFirst = false): array
    {
        return $this->inputs([$root], null, $names, $childrenFirst);
    }

    /**
     * Resolves a list of owned inputs in an optional enclosing WITH scope.
     *
     * @param list<Node> $roots Input occurrences, not replacement query nodes
     * @param bool $childrenFirst Whether each owner follows its completed operands
     * @return list<Node> Reached nodes and their common-table dependencies
     */
    public function inputs(array $roots, ?With $with = null, Comparison $names = Comparison::Sensitive, bool $childrenFirst = false): array
    {
        /**
 * @var list<array<string, int>> $scopes
 */
        $scopes = [[]];
        /**
 * @var list<array{CommonTableExpression, int}> $definitions
 */
        $definitions = [];
        $initial = $with === null ? 0 : $this->define($with, $names, $scopes, $definitions, 0);
        $pending = array_map(static fn (Node $root): array => [$root, $initial, false], array_reverse($roots));
        $seen = [];
        $found = [];
        while ($pending !== []) {
            [$node, $scope, $completed] = array_pop($pending);
            if ($completed) {
                $found[] = $node;
                continue;
            }
            $key = spl_object_id($node) . ':' . $scope;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($childrenFirst) {
                $pending[] = [$node, $scope, true];
            } else {
                $found[] = $node;
            }
            $with = $this->with($node);
            if ($with !== null) {
                $scope = $this->define($with, $names, $scopes, $definitions, $scope);
            }
            if (($node instanceof TableReference || $node instanceof ExplicitTable) && $node->name()->schema === null) {
                $definition = $scopes[$scope][$names->fold($node->name()->name->value)] ?? null;
                if ($definition !== null) {
                    $pending[] = [...$definitions[$definition], false];
                }
            }
            foreach (array_reverse($this->children($node)) as $child) {
                $pending[] = [$child, $scope, false];
            }
        }
        return $found;
    }

    /**
     * @return list<Node> Owned operands, excluding separately bound definitions and inactive write clauses
     */
    public function children(Node $node): array
    {
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery && ($values = $node->values()) !== null) {
            return [$node->into, $values, ...$node->onDuplicate];
        }
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal) {
            return [...($node->condition === null ? [] : [$node->condition]), ...$node->items];
        }
        $children = [];
        foreach (get_object_vars($node) as $property => $value) {
            if ($property === 'with' && $this->with($node) !== null) {
                continue;
            }
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }
        return $children;
    }
    /**
     * Identifies a lexical WITH owner, including retained write instructions.
     */
    public function with(Node $node): ?With
    {
        $with = match (true) {
            $node instanceof QueryExpression,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Delete,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Update,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete => $node->with,
            default => null,
        };
        return $with instanceof With ? $with : null;
    }

    /**
     * Adds ordered CTE bindings while preserving each definition's lexical scope.
     *
     * @param list<array<string, int>> $scopes Visible binding maps
     * @param list<array{CommonTableExpression, int}> $definitions Definition occurrences and their scopes
     */
    public function define(With $with, Comparison $names, array &$scopes, array &$definitions, int $outer): int
    {
        $bindings = $scopes[$outer];
        foreach ($with->tables as $table) {
            $id = count($definitions);
            $scope = count($scopes);
            $previous = $bindings;
            $bindings[$names->fold($table->name->value)] = $id;
            $scopes[] = $with->recursive ? $bindings : $previous;
            $definitions[] = [$table, $scope];
        }
        $scope = count($scopes);
        $scopes[] = $bindings;
        return $scope;
    }

}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
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
     * @return list<Node> Nodes used by the read, including transitively referenced definitions
     */
    public function nodes(Node $root): array
    {
        /** @var list<array<string, int>> $scopes */
        $scopes = [[]];
        /** @var list<array{CommonTableExpression, int}> $definitions */
        $definitions = [];
        /** @var list<array{Node, int}> $pending */
        $pending = [[$root, 0]];
        $seen = [];
        $found = [];
        while ($pending !== []) {
            [$node, $scope] = array_pop($pending);
            $key = spl_object_id($node) . ':' . $scope;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $found[] = $node;
            if ($node instanceof QueryExpression && $node->with instanceof With) {
                $bindings = $scopes[$scope];
                foreach ($node->with->tables as $table) {
                    $id = count($definitions);
                    $definitionScope = count($scopes);
                    $previous = $bindings;
                    $bindings[strtolower($table->name->value)] = $id;
                    $scopes[] = $node->with->recursive ? $bindings : $previous;
                    $definitions[] = [$table, $definitionScope];
                }
                $scope = count($scopes);
                $scopes[] = $bindings;
            }
            if (($node instanceof TableReference || $node instanceof ExplicitTable) && $node->name()->schema === null) {
                $definition = $scopes[$scope][strtolower($node->name()->name->value)] ?? null;
                if ($definition !== null) {
                    $pending[] = $definitions[$definition];
                }
            }
            foreach (array_reverse(get_object_vars($node)) as $property => $value) {
                if ($node instanceof QueryExpression && $property === 'with') {
                    continue;
                }
                foreach (array_reverse(is_array($value) ? $value : [$value]) as $child) {
                    if ($child instanceof Node) {
                        $pending[] = [$child, $scope];
                    }
                }
            }
        }
        return $found;
    }
}

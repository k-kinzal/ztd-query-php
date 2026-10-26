<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;

/**
 * Finds input-name collisions that no catalog or default database can resolve.
 *
 * Query bodies remain templates until read. Only referenced CTE definitions
 * contribute constraints; program branches are all part of their declaration.
 * Case differences and explicitly distinct databases remain binding dependencies.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InputNames
{
    /**
     * Returns a fixed repeated name in an active input namespace.
     */
    public function problem(Node $root): ?M\Server\Problem\NonUniqueTable
    {
        $nodes = (new ReadDependencies())->nodes($root, childrenFirst: true);
        $collisions = [];
        foreach ($nodes as $node) {
            $tables = match (true) {
                $node instanceof M\Query\Select, $node instanceof M\Routine\Program\DeferredSelection => $node->from === null || $node->from instanceof M\Relation\Dual ? [] : [$node->from],
                $node instanceof M\Dml\Update, $node instanceof M\Dml\Problem\Update\ConflictingUpdate, $node instanceof M\Dml\MultipleDelete, $node instanceof M\Routine\Program\Write\DeferredMultipleDelete => $node->tables,
                default => [],
            };
            $problem = (new Structure())->names($tables);
            if ($problem?->relation !== null) {
                $collisions[spl_object_id($problem->relation)] = $problem;
            }
        }
        foreach ($nodes as $node) {
            if (isset($collisions[spl_object_id($node)])) {
                return $collisions[spl_object_id($node)];
            }
        }
        return null;
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Platform\Sqlite\Rules\Typing\Functions;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Statement\Node;
use SqlSemantics\Validation\ValueGraph;

/**
 * Finds the aggregate function calls among the parts of a selection.
 *
 * Rule: SQLITE-AGGREGATE-QUERY-001. A selection without GROUP BY that calls
 * an aggregate function in its result columns, HAVING or ORDER BY returns
 * one row even for an empty input; a column of the input read outside an
 * aggregate is then NULL, so in such a selection every input column can be
 * NULL. A call inside a nested query counts for the enclosing selection as
 * well, which can only make more columns able to be NULL. Terminates: the
 * structure is a finite tree walked once.
 * Source: https://sqlite.org/lang_select.html#bare_columns_in_an_aggregate_query.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Aggregation
{
    /**
     * Tells whether any of the nodes contains a call of a built-in aggregate function without a window.
     *
     * @param list<Node> $nodes
     */
    public function aggregates(array $nodes): bool
    {
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\Sqlite\\Statement\\']);
        $functions = new Functions();
        foreach ($nodes as $node) {
            foreach ($graph->objects($node) as $object) {
                if ($object instanceof FunctionCall && $object->over === null && $functions->aggregates($object->name->value, count($object->arguments))) {
                    return true;
                }
            }
        }

        return false;
    }
}

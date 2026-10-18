<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Enforces the query grammar shared by CREATE VIEW and ALTER VIEW.
 *
 * A view query cannot read session inputs, redirect its result, or invoke
 * the legacy PROCEDURE ANALYSE result processor.
 * The restriction applies through query wrappers and set operations.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/create-view.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewQueries
{
    /**
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the query has an intrinsic view-body restriction
     */
    public function check(Query $query): void
    {
        $problem = $this->problem($query);
        Check::input($problem === null, $problem?->message() ?? 'A view query has a persistent read role.');
        $pending = [$query];
        $seen = new SplObjectStorage();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node && !isset($seen[$value])) {
                $seen[$value] = true;
                Check::input(!$value instanceof ProcedureAnalyse, 'A view query cannot invoke PROCEDURE ANALYSE.');
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
    }

    /**
     * Answers the first variable or destination in the body's structural clause order.
     */
    public function problem(Node $query): ?\SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction
    {
        $node = $this->occurrence($query);
        return $node === null ? null : ($node instanceof \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination ? \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Into : \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Variable);
    }

    /**
     * Answers the first reached session input or destination in written clause order.
     */
    public function occurrence(Node $query): ?Node
    {
        $reached = array_fill_keys(array_map(spl_object_id(...), (new ReadDependencies())->nodes($query)), true);
        foreach ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewClauses())->nodes($query) as $node) {
            if (!isset($reached[spl_object_id($node)])) {
                continue;
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable || $node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable || $node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter) {
                return $node;
            }
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination) {
                return $node;
            }
        }
        return null;
    }
}

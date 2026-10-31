<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Statement\Node;

/**
 * Defines the modifier domain of the common table expressions a statement reads.
 *
 * The server prepares a common table expression when the statement references it, by
 * its exact name, directly or through another common table expression it reads, and
 * refuses HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS and SQL_CACHE
 * (ER_CANT_USE_OPTION_HERE) and ALL with DISTINCT (ER_WRONG_USAGE) in its blocks before
 * any table is opened; a definition nobody references is read and left alone. Verified
 * on MySQL 8.4.7: `WITH c AS (SELECT HIGH_PRIORITY 1) SELECT * FROM c, missing_t` -> 1234,
 * `WITH c AS (SELECT HIGH_PRIORITY 1) SELECT 2` and `... SELECT * FROM C` are accepted,
 * `WITH c AS (SELECT HIGH_PRIORITY 1), d AS (SELECT * FROM c) SELECT * FROM d` -> 1234,
 * also in DELETE, UPDATE, INSERT ... WITH and inside a stored program body.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReachedTables
{
    /**
     * Answers the problem of the first reached common table expression whose blocks carry such a modifier, or null.
     *
     * @param list<Node|list<Node>|null> $parts The parts of the statement other than its WITH clause: nodes, lists of nodes, or null for an absent clause
     */
    public function problem(?WithClause $with, array $parts): RestrictedSelectOption|SelectOptionConflict|null
    {
        if (!$with instanceof With) {
            return null;
        }
        foreach ((new ReadDependencies())->inputs($this->nodes($parts), $with) as $node) {
            if ($node instanceof CommonTableExpression) {
                $problem = (new DerivedOptions())->problem($node->query) ?? (new StatementQuery())->problem($node->query);
                if ($problem !== null) {
                    return $problem;
                }
            }
        }

        return null;
    }

    /**
     * Collects the nodes among the parts in written order, leaving out absent clauses.
     *
     * @param list<Node|list<Node>|null> $parts
     * @return list<Node>
     */
    public function nodes(array $parts): array
    {
        $nodes = [];
        foreach ($parts as $part) {
            if ($part instanceof Node) {
                $nodes[] = $part;
            } elseif ($part !== null) {
                array_push($nodes, ...$part);
            }
        }

        return $nodes;
    }
}

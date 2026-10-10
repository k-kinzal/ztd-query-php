<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Assigns aggregate occurrences to the block whose rows they summarize.
 *
 * Rule: MYSQL-AGGREGATE-OWNER-001. An aggregate that reads only outer columns
 * belongs to the outermost permitted block no farther out than its nearest
 * referenced block. SELECT, HAVING and ORDER BY permit aggregation; WHERE
 * does not. Without column references the aggregate stays in its written block.
 * Window functions do not participate. Terminates: finite argument trees and
 * enclosing environments. Source:
 * https://dev.mysql.com/doc/refman/8.0/en/window-function-optimization.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AggregateOwnership
{
    /**
     * Registers a resolved aggregate with its owning block.
     */
    public function register(SetFunction $call, Derivation $derivation, Environment $environment): void
    {
        if (!$call->aggregates() || $environment->aggregation === null) {
            return;
        }
        $initial = $environment->aggregatesAllowed ? $environment->aggregation : null;
        $owner = $initial;
        $references = $this->references($call, $derivation->facts());
        if ($references !== []) {
            for ($position = $environment; $position !== null; $position = $position->outer) {
                $scope = $position->aggregation;
                if ($scope === null) {
                    continue;
                }
                if ($position->aggregatesAllowed) {
                    $owner = $scope;
                }
                foreach ($references as $relation) {
                    if ($scope->contains($relation)) {
                        $owner?->register($call);

                        return;
                    }
                }
            }
        }
        $initial?->register($call);
    }

    /**
     * Finds column occurrences in the arguments, without entering nested queries.
     *
     * @return list<Relation>
     */
    public function references(SetFunction $call, Facts $facts): array
    {
        $pending = array_values(get_object_vars($call));
        $relations = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
                continue;
            }
            if (!is_object($value) || $value instanceof Query) {
                continue;
            }
            if ($value instanceof Scalar && $facts->covers($value)) {
                $fact = $facts->scalar($value);
                if ($fact->replacement !== null) {
                    $pending[] = $fact->replacement;
                    continue;
                }
                $resolution = $fact->resolution;
                if ($resolution instanceof ResolvedColumn && !$resolution->resultReference) {
                    $relations[spl_object_id($resolution->relation)] = $resolution->relation;
                }
            }
            array_push($pending, ...array_values(get_object_vars($value)));
        }

        return array_values($relations);
    }
}

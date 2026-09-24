<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Retrieval;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the query whose rows a SELECT ... INTO statement stores instead of returning.
 * @visibility SqlSemantics
 */
final class RetrievedQuery
{
    /**
     * The query uses the statement dialect, and the INTO form belongs to that dialect.
     * @throws InvalidStructure
     */
    public static function check(Origin $origin, BoundQuery $query, Dialect $dialect): void
    {
        if ($origin->dialect !== $dialect || $query->origin->dialect !== $dialect) {
            throw new InvalidStructure('This SELECT ... INTO form and its query belong to one SQL dialect.');
        }
    }

    /**
     * Returns the number of result columns, or null when an unresolved wildcard leaves it unknown.
     */
    public static function width(BoundQuery $query): ?int
    {
        foreach (\SqlSemantics\Model\Traversal\Expressions::all($query) as $expression) {
            if ($expression instanceof \SqlSemantics\Model\Scalar\Reference\Wildcard) {
                return null;
            }
        }
        $columns = count($query->resultColumns());
        return $columns === 0 ? null : $columns;
    }
}

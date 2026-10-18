<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Statement\Query;

/**
 * The output width fixed by a query's structure, before catalog resolution.
 *
 * A wildcard or TABLE query leaves its width dependent on declarations. An
 * explicit projection fixes its width even when column names are unresolved.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryWidth
{
    /**
     * Answers the fixed width, or null when the output depends on the catalog.
     */
    public function of(\SqlSemantics\Statement\Query|Query\Set\LeadingUnion $query, bool $exists = false): ?int
    {
        return match (true) {
            $query instanceof Query\QueryStatement, $query instanceof Query\ParenthesizedQuery => $this->of($query->query, $exists),
            $query instanceof Query\QueryExpression => $this->of($query->body, $exists),
            $query instanceof Query\Set\SetOperation, $query instanceof Query\Set\OrderedSetOperation, $query instanceof Query\Set\LeadingUnion => $this->of($query->left, $exists),
            $query instanceof Query\ValuesQuery, $query instanceof Query\With\DeferredValuesQuery => count($query->rows[0]->values),
            $query instanceof Query\Select => $exists || count(array_filter($query->items, static fn ($item): bool => !$item instanceof Query\SelectExpression)) === 0 ? count($query->items) : null,
            default => null,
        };
    }
}

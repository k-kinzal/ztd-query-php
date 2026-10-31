<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Preparation;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\CallEffects;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\ProjectionSources;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupKeys;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Retains quantified-subquery NULL attributes independently of runtime truth.
 * A fixed nonnullable projection can acquire an empty-input NULL attribute;
 * nullable outputs and predicates with runtime effects retain the outer attribute.
 * Verified using legacy and modern result metadata and populated query histories.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QuantifiedAttributes
{
    /**
     * Derives legacy attributes after distinguishing membership from ordered comparison.
     * MySQL 5.6's tableless nullable operand retains its attribute across WHERE.
     */
    public function legacy(Query $query, Nullability $outer, Nullability $bound, GrammarRelease $release, bool $ordered): Nullability
    {
        if ($release === GrammarRelease::MySql5651 && $bound === Nullability::Nullable && (new \SqlSemantics\Platform\MySql\Rules\Expression\ScalarReduction())->predicate($query, $release) !== null) {
            return $bound;
        }
        return $ordered && !(new \SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows())->tableless($query, true) && $this->nullable($query) ? Nullability::Nullable : $outer;
    }

    /**
     * Derives modern attributes for unreduced nonmembership comparisons.
     * MySQL 8.0 reports only the inner attribute when the outer operand is nullable;
     * 8.1 and later preserve the combined attribute. Unfiltered sets and runtime
     * filters retain their bound attributes rather than acquiring an empty-input flag.
     */
    public function modern(Query $query, Nullability $outer, Nullability $bound, GrammarRelease $release, bool $equality): Nullability
    {
        if ($release === GrammarRelease::MySql8044 && $outer === Nullability::Nullable) {
            return $query->queryInformation()->shape->slots[0]->nullability ?? $bound;
        }
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        $set = $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
        return $equality || ($set && (new \SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows())->tableless($query, true)) || $this->runtimeFilter($query) ? $bound : Nullability::Nullable;
    }

    /**
     * Tells whether an ordered comparison acquires the legacy empty-input attribute.
     */
    public function nullable(Query $query): bool
    {
        if (($query->queryInformation()->shape->slots[0]->nullability ?? null) !== Nullability::NotNull || !(new ProjectionSources())->constant($query)) {
            return false;
        }
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        return !$query instanceof Select || $query->where === null || $this->fixed($query->where);
    }

    /**
     * Tells whether a simple block's filter retains runtime dependencies.
     */
    public function runtimeFilter(Query $query): bool
    {
        return $query instanceof Select && $query->where !== null && !$this->fixed($query->where);
    }

    /**
     * Excludes row references and per-read effects, including user-variable accesses.
     */
    public function fixed(Node $node): bool
    {
        $keys = new GroupKeys();
        if (!$keys->constant($node) || (new CallEffects())->uncacheable($node, GrammarRelease::MySql5744)) {
            return false;
        }
        foreach ($keys->children($node) as $child) {
            if (!$this->fixed($child)) {
                return false;
            }
        }
        return true;
    }
}

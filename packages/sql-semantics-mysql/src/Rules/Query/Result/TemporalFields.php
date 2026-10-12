<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Result;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Scalar;

/**
 * Separates temporal expression collations from the fields used between query phases.
 *
 * Grouped aggregate fields are stored, while expressions computed over those fields
 * retain their logical types. Direct ROLLUP emits its aggregate values without storing
 * them again. DISTINCT, ordering of bare aggregates, and window stages introduce fields.
 * Separate unnamed windows can expose the fields of earlier stages to introspection;
 * calls sharing the final named window retain their logical collation together.
 * Verified through SQL on MySQL 8.0 and 8.4; legacy field behavior is preserved.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TemporalFields
{
    /**
     * Tells whether an output expression is represented by a stored temporal field.
     */
    public function stored(Select $select, ?Scalar $expression, GrammarRelease $release = GrammarRelease::MySql847): bool
    {
        $rollup = new RollupItems();
        if ($expression instanceof ScalarSubquery || ($select->groupBy === null && $expression !== null && $rollup->containsWindow([$expression]))) {
            return false;
        }
        if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return true;
        }
        $distinct = in_array(SelectOption::Distinct, $select->options, true);
        $expression = $expression === null ? null : (new Matching())->unwrap($expression);
        $computed = $expression !== null && !$expression instanceof SetFunction && (new Aggregation())->aggregates([$expression]);
        $directRollup = $rollup->rolls($select) && $select->orderBy === [] && ($select->late === null || $select->late->orderBy === []);

        return $distinct || $rollup->windowed($select) || (!$computed && !$directRollup);
    }

    /**
     * Tells whether a temporal window read still exposes its logical expression collation.
     * A later window stores previous reads; grouped expressions remain outside that copy
     * unless a non-ROLLUP DISTINCT query materializes them too.
     */
    public function retainsWindow(Select $select, Scalar $call, bool $last): bool
    {
        return $last || ((new Aggregation())->aggregates([$call]) && (!in_array(SelectOption::Distinct, $select->options, true) || $select->groupBy?->modifier !== null));
    }
}

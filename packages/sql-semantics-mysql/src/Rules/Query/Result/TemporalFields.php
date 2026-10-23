<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Result;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
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
     * Resolves legacy direct MIN/MAX result metadata over computed temporal arguments.
     *
     * MySQL 5.6 and 5.7 report these values as VARCHAR while retaining temporal
     * expression semantics. A stored group or DISTINCT output reports the temporal
     * field again. Direct column arguments keep their declared field throughout.
     * Verified through PDO on MySQL 5.6.51 and 5.7.44.
     */
    public function reported(Select $select, ?Scalar $expression, Domain $domain, GrammarRelease $release, ?Facts $facts = null): Domain
    {
        $expression = $expression === null ? null : (new Matching())->unwrap($expression);
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $distinct = in_array(SelectOption::Distinct, $select->options, true);
        $direct = $select->groupBy === null || ((new RollupItems())->rolls($select) && $select->orderBy === []);
        $argument = $expression instanceof Aggregate ? ($expression->arguments[0] ?? null) : null;
        if (!$legacy || $distinct || !$direct || !$domain->kind->temporal() || $argument === null || $facts === null || !$facts->covers($argument) || $facts->scalar($argument)->resolution instanceof ResolvedColumn) {
            return $domain;
        }

        return new Domain($domain->kind, Field::VarString, $domain->length, $domain->decimals, $domain->unsigned, $domain->collation, $domain->members, $domain->coercibility);
    }

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

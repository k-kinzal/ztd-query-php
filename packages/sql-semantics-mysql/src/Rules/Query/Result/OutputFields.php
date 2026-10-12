<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Result;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupKeys;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Scalar;

/**
 * Resolves output storage independently of the logical types of parent expressions.
 *
 * Strings computed over aggregates retain expression metadata until DISTINCT stores
 * them. A parent of a grouped window remains computed after its window; ROLLUP keeps
 * that parent outside DISTINCT storage too. Bare aggregates use grouped fields unless
 * implicit aggregation, constant grouping or direct ROLLUP emits them. Verified through SQL metadata on MySQL 8.4.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OutputFields
{
    /**
     * Answers the domain exposed by an output field at the final query phase.
     */
    public function domain(Select $select, ?Scalar $expression, Domain $domain, GrammarRelease $release, ?Facts $facts = null, ?Settings $settings = null): Domain
    {
        $reported = (new TemporalFields())->reported($select, $expression, $domain, $release, $facts);
        if ($reported !== $domain || $this->direct($select, $release, $facts)) {
            return $reported;
        }
        $distinct = in_array(SelectOption::Distinct, $select->options, true);
        if ($select->groupBy === null && !$distinct && !(new RollupItems())->windowed($select)) {
            return $domain;
        }
        if ($domain->kind->temporal() && !$domain->collation->bytes() && (new TemporalFields())->stored($select, $expression, $release)) {
            return (new Materialization())->nothing($domain);
        }
        if ($this->storedJson($select, $expression, $domain, $facts)) {
            return (new Materialization())->windowed($domain, $release, $expression);
        }
        if ($expression === null || $domain->kind !== Kind::String || !(new Aggregation())->aggregates([$expression]) || $this->deferred($select, $expression, $facts)) {
            return $domain;
        }
        if (!$this->stored($select, $expression, $facts, $release)) {
            return $domain;
        }
        $target = (new Matching())->unwrap($expression);
        if ($target instanceof GroupConcat) {
            return (new Materialization())->computed($domain, $target, $settings ?? new Settings($domain->collation), $release);
        }
        if ($this->copied($expression, $facts)) {
            return (new Materialization())->column($domain, release: $release);
        }

        return $target instanceof SetFunction || $target instanceof WindowFunction ? (new Materialization())->windowed($domain, $release, $expression) : (new Materialization())->windowParent($domain, $release, $expression);
    }

    /**
     * Identifies a bare JSON aggregate stored between varying groups and their window stage.
     */
    public function storedJson(Select $select, ?Scalar $expression, Domain $domain, ?Facts $facts = null): bool
    {
        $target = $expression === null ? null : (new Matching())->unwrap($expression);

        return $domain->kind === Kind::Json && !(new GroupKeys())->direct($select, $facts) && (new RollupItems())->windowed($select)
            && $target instanceof SetFunction && $target->aggregates();
    }

    /**
     * Tells whether MIN or MAX copies a resolved input column without window conversion.
     * Such output retains the source field type and its byte bound when stored again.
     */
    public function copied(?Scalar $expression, ?Facts $facts): bool
    {
        $expression = $expression === null ? null : (new Matching())->unwrap($expression);
        if (!$expression instanceof \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate || $expression->over !== null || !in_array($expression->function, [\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Minimum, \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Maximum], true)) {
            return false;
        }
        $argument = (new Matching())->unwrap($expression->arguments[0]);
        $resolution = $facts !== null && $facts->covers($argument) ? $facts->scalar($argument)->resolution : null;

        return $resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn;
    }

    /**
     * Tells whether an aggregate output becomes a stored field at the grouping or window stage.
     * Bare aggregates use stored fields; computed parents retain their expression metadata
     * until DISTINCT stores them. Direct ROLLUP retains its expression fields.
     */
    public function stored(Select $select, Scalar $expression, ?Facts $facts = null, GrammarRelease $release = GrammarRelease::MySql847): bool
    {
        if ($this->direct($select, $release, $facts)) {
            return false;
        }
        $rollup = new RollupItems();
        $expression = (new Matching())->unwrap($expression);
        $bare = $expression instanceof SetFunction || $expression instanceof WindowFunction;
        $distinct = in_array(SelectOption::Distinct, $select->options, true);
        $directRollup = $rollup->rolls($select) && !$rollup->windowed($select) && $select->orderBy === [] && ($select->late === null || $select->late->orderBy === []);
        $stored = $rollup->containsWindow([$expression]) ? ($bare || ($distinct && (!$rollup->rolls($select) || !(new GroupKeys())->varying($select, $facts)))) : ($distinct || ($bare && !$directRollup));

        return $stored;
    }

    /**
     * Tells whether a ROLLUP output is computed after duplicate removal at a window stage.
     * With varying group keys, aggregate parents are not DISTINCT keys; bare calls are.
     * Constant ROLLUP groups store their parent outputs and compare them as keys.
     * If no nonconstant keys remain, all input rows reach the deferred expressions.
     * Verified through SQL on MySQL 8.4.
     */
    public function deferred(Select $select, ?Scalar $expression, ?Facts $facts = null): bool
    {
        if ($expression === null || !(new RollupItems())->rolls($select) || !(new RollupItems())->windowed($select)) {
            return false;
        }
        $expression = (new Matching())->unwrap($expression);

        return (new GroupKeys())->varying($select, $facts) && !$expression instanceof SetFunction && !$expression instanceof WindowFunction && (new Aggregation())->aggregates([$expression]);
    }

    /**
     * Identifies direct aggregate fields on releases that eliminate constant grouping.
     * Legacy output keeps its existing grouped-field rules.
     */
    public function direct(Select $select, GrammarRelease $release, ?Facts $facts = null): bool
    {
        return !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && (new GroupKeys())->direct($select, $facts);
    }

}

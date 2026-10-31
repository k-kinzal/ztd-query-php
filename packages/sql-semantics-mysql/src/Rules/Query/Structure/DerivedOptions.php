<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Statement\Query;

/**
 * Defines the modifier domain of the query blocks that are not the first block of their statement: derived tables, later set operands and expression subqueries.
 *
 * HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS and legacy SQL_CACHE cannot occur in
 * these blocks in any release (ER_CANT_USE_OPTION_HERE), including uncalled stored definitions;
 * MySQL 5.6 and 5.7 also refuse SQL_NO_CACHE there, which later releases accept.
 * Nested expressions and CTE declarations have their own query boundaries.
 * Verified through SQL on MySQL 5.6.51, 5.7.44, 8.0.44 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DerivedOptions
{
    /**
     * Answers the problem the release reports first in the blocks of a query that is not the first block of its statement, or null when it accepts every block.
     *
     * Without a release, the modifiers every release refuses are answered: the invariant of the types that hold such a query.
     */
    public function problem(Query $query, GrammarRelease $release = GrammarRelease::MySql847): RestrictedSelectOption|SelectOptionConflict|null
    {
        foreach ($this->blocks($query) as $block) {
            $problem = $this->block($block->options, $release);
            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * Answers the problem the release reports first for the modifiers of one such block, in the order the server checks them.
     *
     * MySQL 5.6 refuses, in written order, the first of HIGH_PRIORITY, SQL_BUFFER_RESULT,
     * SQL_CALC_FOUND_ROWS, SQL_CACHE and SQL_NO_CACHE. MySQL 5.7 refuses a query cache modifier
     * written last, then ALL with DISTINCT, then HIGH_PRIORITY, SQL_BUFFER_RESULT and
     * SQL_CALC_FOUND_ROWS in that order, then the last query cache modifier. Later releases
     * refuse ALL with DISTINCT when the block has a restricted modifier, then HIGH_PRIORITY,
     * SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS and SQL_CACHE in that order.
     *
     * @param list<SelectOption> $options The modifiers in written order
     */
    public function block(array $options, GrammarRelease $release): RestrictedSelectOption|SelectOptionConflict|null
    {
        $priority = [SelectOption::HighPriority, SelectOption::BufferResult, SelectOption::CalcFoundRows];
        if ($release === GrammarRelease::MySql5651) {
            foreach ($options as $option) {
                if (in_array($option, [...$priority, SelectOption::Cache, SelectOption::NoCache], true)) {
                    return new RestrictedSelectOption($option);
                }
            }

            return null;
        }
        $legacy = $release === GrammarRelease::MySql5744;
        $restricted = array_values(array_filter($legacy ? [...$priority, SelectOption::Cache, SelectOption::NoCache] : [...$priority, SelectOption::Cache], static fn (SelectOption $option): bool => in_array($option, $options, true)));
        if ($restricted === []) {
            return null;
        }
        $cache = array_values(array_filter($options, static fn (SelectOption $option): bool => $option === SelectOption::Cache || $option === SelectOption::NoCache));
        $last = $options[count($options) - 1];
        if ($legacy && ($last === SelectOption::Cache || $last === SelectOption::NoCache)) {
            return new RestrictedSelectOption($last);
        }
        if (in_array(SelectOption::All, $options, true) && in_array(SelectOption::Distinct, $options, true)) {
            return new SelectOptionConflict(SelectOption::All, SelectOption::Distinct);
        }
        foreach ($priority as $option) {
            if (in_array($option, $options, true)) {
                return new RestrictedSelectOption($option);
            }
        }

        return new RestrictedSelectOption($legacy ? $cache[count($cache) - 1] : SelectOption::Cache);
    }

    /**
     * Answers the query-expression spine in written order, stopping at each block.
     *
     * @return list<Select|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection> The blocks that belong to this derived query
     */
    public function blocks(Query $query): array
    {
        $blocks = [];
        $pending = [$query];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody) {
                $blocks[] = $node->selection;
            } elseif ($node instanceof Select) {
                $blocks[] = $node;
            } elseif (is_array($node)) {
                array_push($pending, ...array_reverse(array_values($node)));
            } elseif ($node instanceof Query || $node instanceof LeadingUnion) {
                array_push($pending, ...array_reverse(array_values(get_object_vars($node))));
            }
        }

        return $blocks;
    }
}

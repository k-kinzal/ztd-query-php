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
 * Defines the modifier domain of the query blocks forming a derived table.
 *
 * HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS and legacy SQL_CACHE cannot occur in
 * these blocks, including later set operands and uncalled stored definitions.
 * Nested expressions and CTE declarations have their own query boundaries.
 * Verified through SQL on MySQL 5.6, 5.7, 8.0 and 8.4.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DerivedOptions
{
    /**
     * Answers a role violation, preserving the versioned quantifier precedence.
     */
    public function problem(Query $query, GrammarRelease $release = GrammarRelease::MySql847): RestrictedSelectOption|SelectOptionConflict|null
    {
        foreach ($this->blocks($query) as $block) {
            $priority = [SelectOption::HighPriority->value, SelectOption::BufferResult->value, SelectOption::CalcFoundRows->value, SelectOption::Cache->value];
            $written = array_map(static fn (SelectOption $option): string => $option->value, $block->options);
            $restricted = array_values($release === GrammarRelease::MySql5651 ? array_intersect($written, $priority) : array_intersect($priority, $written));
            if ($restricted === []) {
                continue;
            }
            if ($release !== GrammarRelease::MySql5651 && in_array(SelectOption::All, $block->options, true) && in_array(SelectOption::Distinct, $block->options, true)) {
                return new SelectOptionConflict(SelectOption::All, SelectOption::Distinct);
            }

            return new RestrictedSelectOption(SelectOption::from($restricted[0]));
        }

        return null;
    }

    /**
     * Answers the query-expression spine in written order, stopping at each block.
     *
     * @return list<Select> The blocks that belong to this derived query
     */
    public function blocks(Query $query): array
    {
        $blocks = [];
        $pending = [$query];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof Select) {
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

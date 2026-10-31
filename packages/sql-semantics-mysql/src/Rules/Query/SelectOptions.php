<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;

/**
 * Raises what the server reports while it parses the modifiers of a query block.
 *
 * Rule: MYSQL-SELECT-OPTIONS-001. The deprecated modifiers warn in written
 * order (MYSQL-DEPRECATION-001). MySQL 5.6 and 5.7 refuse a query cache
 * modifier that meets another one: 5.6 compares it with the first one the
 * block writes, 5.7 only with the modifier written just before it, so another
 * modifier between the two separates them (verified on live 5.6.51 and 5.7.44
 * servers). The same modifier twice is ER_DUP_ARGUMENT, SQL_CACHE with
 * SQL_NO_CACHE is ER_WRONG_USAGE; the parse stops at the second one, after
 * its warning, so the lowering refuses the input there, and a derivation under
 * a legacy release reports the conflict of a constructed block. MySQL 5.6 also
 * refuses, at the modifier, the first of HIGH_PRIORITY, SQL_BUFFER_RESULT,
 * SQL_CALC_FOUND_ROWS, SQL_CACHE and SQL_NO_CACHE in a block that is not the
 * first of its statement. Terminates: one pass over the modifiers. Source:
 * https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectOptions
{
    /**
     * Raises the warnings of the modifiers of a block and reports the conflicts the selected release refuses: the first conflict of its query cache modifiers, and ALL with DISTINCT outside MySQL 5.6.
     */
    public function raise(Select|DeferredSelection $select, Derivation $derivation): void
    {
        $grammar = $derivation->context->profile->grammar;
        foreach ($this->notices($select, $grammar) as $notice) {
            $derivation->warn($notice, $select, false);
        }
        $conflict = $this->conflict($select->options, $grammar);
        if ($conflict !== null) {
            $derivation->report($conflict[1]);
        }
        if ($grammar !== GrammarRelease::MySql5651 && in_array(SelectOption::All, $select->options, true) && in_array(SelectOption::Distinct, $select->options, true)) {
            $derivation->report(new SelectOptionConflict(SelectOption::All, SelectOption::Distinct));
        }
    }

    /**
     * Returns the deprecation warnings of a block's modifiers in written order, without deriving its operands.
     *
     * @return list<Deprecation> The warnings the release gives
     */
    public function notices(Select|DeferredSelection $select, GrammarRelease $grammar): array
    {
        $notices = [];
        foreach ($select->options as $option) {
            $construct = match ($option) {
                SelectOption::CalcFoundRows => Deprecated::CalcFoundRows,
                SelectOption::NoCache => Deprecated::NoCache,
                SelectOption::Cache => Deprecated::Cache,
                SelectOption::All, SelectOption::Distinct, SelectOption::StraightJoin, SelectOption::HighPriority, SelectOption::SmallResult, SelectOption::BigResult, SelectOption::BufferResult => null,
            };
            if ($construct !== null && $construct->warnedIn($grammar)) {
                $notices[] = new Deprecation($construct);
            }
        }

        return $notices;
    }

    /**
     * Finds the first query cache modifier a legacy release refuses beside an earlier one of the same block; later releases accept SQL_NO_CACHE any number of times and have no SQL_CACHE.
     *
     * @param list<SelectOption> $options The modifiers in written order
     * @return array{int, SelectOptionConflict}|null The position of the refused modifier and the conflict, or null when the release accepts the modifiers
     */
    public function conflict(array $options, GrammarRelease $grammar): ?array
    {
        if ($grammar !== GrammarRelease::MySql5651 && $grammar !== GrammarRelease::MySql5744) {
            return null;
        }
        $previous = null;
        $first = null;
        foreach ($options as $index => $option) {
            $cache = $option === SelectOption::Cache || $option === SelectOption::NoCache;
            $earlier = $grammar === GrammarRelease::MySql5651 ? $first : $previous;
            if ($cache && $earlier !== null) {
                return [$index, new SelectOptionConflict($earlier, $option)];
            }
            $previous = $cache ? $option : null;
            $first ??= $cache ? $option : null;
        }

        return null;
    }

    /**
     * Finds the first modifier MySQL 5.6 refuses, while parsing it, in a query block that is not the first of its statement: HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS, SQL_CACHE or SQL_NO_CACHE.
     *
     * @param list<SelectOption> $options The modifiers in written order
     * @return int|null The position of the refused modifier, or null when the block has none
     */
    public function placement(array $options): ?int
    {
        foreach ($options as $index => $option) {
            if (in_array($option, [SelectOption::HighPriority, SelectOption::BufferResult, SelectOption::CalcFoundRows, SelectOption::Cache, SelectOption::NoCache], true)) {
                return $index;
            }
        }

        return null;
    }
}

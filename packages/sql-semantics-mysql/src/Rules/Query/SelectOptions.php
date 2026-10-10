<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

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
 * its warning. Terminates: one pass over the modifiers. Source:
 * https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectOptions
{
    /**
     * Raises the warnings of the modifiers of a block and the first conflict of its query cache modifiers.
     */
    public function raise(Select $select, Derivation $derivation): void
    {
        $grammar = $derivation->context->profile->grammar;
        $previous = null;
        $first = null;
        foreach ($select->options as $option) {
            $construct = match ($option) {
                SelectOption::CalcFoundRows => Deprecated::CalcFoundRows,
                SelectOption::NoCache => Deprecated::NoCache,
                SelectOption::Cache => Deprecated::Cache,
                SelectOption::All, SelectOption::Distinct, SelectOption::StraightJoin, SelectOption::HighPriority, SelectOption::SmallResult, SelectOption::BigResult, SelectOption::BufferResult => null,
            };
            if ($construct !== null) {
                Deprecation::raise($construct, $derivation, $select, false);
            }
            $cache = $option === SelectOption::Cache || $option === SelectOption::NoCache;
            $earlier = $grammar === GrammarRelease::MySql5651 ? $first : ($grammar === GrammarRelease::MySql5744 ? $previous : null);
            if ($cache && $earlier !== null) {
                $problem = new CacheOptionConflict($earlier, $option);
                $derivation->report($problem);
                $derivation->warn(new ParseFailure($problem, true));

                return;
            }
            $previous = $cache ? $option : null;
            $first ??= $cache ? $option : null;
        }
    }
}

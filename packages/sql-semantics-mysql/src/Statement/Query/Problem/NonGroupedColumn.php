<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A column a grouped or DISTINCT query reads that its groups do not determine, a problem under the sql_mode ONLY_FULL_GROUP_BY.
 *
 * The server refuses the query only when the session runs with ONLY_FULL_GROUP_BY, the default
 * mode; without it the query reads the column of an arbitrary row of each group. The column is
 * named `database.table.column` after the name the query reads the table by.
 *
 * @visibility public
 * @example Reading the column a grouped query does not determine
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
 *     $semantics->analyze('SELECT b FROM t GROUP BY a', [$table])->facts->diagnostics[0]->message() // => "Expression #1 of SELECT list is not in GROUP BY clause and contains nonaggregated column '(current).t.b' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by"
 */
final class NonGroupedColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param GroupingRule $rule The rule the column breaks
     * @param bool $ordering Whether the expression is an ORDER BY key rather than a select item
     * @param bool $having Whether the expression is the HAVING condition
     * @param int $position The position of the expression in its list, counted from 1
     * @param string $column The column as the server names it
     */
    public function __construct(public readonly GroupingRule $rule, public readonly bool $ordering, public readonly int $position, public readonly string $column, public readonly bool $having = false)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        $list = $this->having ? 'HAVING clause' : ($this->ordering ? 'ORDER BY clause' : 'SELECT list');

        return match ($this->rule) {
            GroupingRule::NotDetermined => sprintf("Expression #%d of %s is not in GROUP BY clause and contains nonaggregated column '%s' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by", $this->position, $list, $this->column),
            GroupingRule::WithoutGroupBy => sprintf("In aggregated query without GROUP BY, expression #%d of %s contains nonaggregated column '%s'; this is incompatible with sql_mode=only_full_group_by", $this->position, $list, $this->column),
            GroupingRule::NotSelected => sprintf("Expression #%d of ORDER BY clause is not in SELECT list, references column '%s' which is not in SELECT list; this is incompatible with DISTINCT", $this->position, $this->column),
        };
    }
}

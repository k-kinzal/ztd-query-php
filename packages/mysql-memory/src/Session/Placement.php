<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use Closure;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Node;

/**
 * Finds the query block options the server refuses where they are written, while it parses a statement.
 *
 * ALL and DISTINCT exclude each other in a query block (ER_WRONG_USAGE). HIGH_PRIORITY,
 * SQL_BUFFER_RESULT and SQL_CALC_FOUND_ROWS belong to the first query block of the statement, or
 * of the query whose rows an INSERT or a CREATE TABLE writes; any other block, a later operand of
 * a set operation, a subquery, a derived table or a common table, has the first of them in that
 * order refused (ER_CANT_USE_OPTION_HERE); a statement of a stored program is checked when it
 * runs, as a statement of its own. The blocks are checked in written order, each before
 * the blocks written in it (verified on a live 8.4 server). MySQL 5.6 and 5.7 also refuse a query
 * cache modifier in any of those other blocks, and INTO in a union operand but the last; the
 * first block of the query of a view may write one (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 *
 * @visibility MySqlMemory
 */
final class Placement
{
    /**
     * Raises the error of the first query block whose options the server refuses.
     *
     * @param Closure(Select, bool): void|null $after A check of each block in written order: called with false after its options, and with true where the block ends, after the blocks written in it, as the server checks the variables of its INTO and its LIMIT
     * @throws \MySqlMemory\Error\SqlError When a block has such options
     */
    public function check(Node $statement, GrammarRelease $release = GrammarRelease::MySql847, ?Closure $after = null): void
    {
        $first = $this->first($this->carrier($statement, $release));
        $program = \MySqlMemory\Command\Dispatcher::program($statement);
        $boundaries = $this->boundaries($statement, $release);
        $open = [];
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            $open = $this->closed($open, $select, $after);
            $open[] = $select;
            if (in_array(spl_object_id($select), $boundaries, true)) {
                throw StatementError::WrongUsage->error('UNION', 'INTO');
            }
            $last = $select->options === [] ? null : $select->options[count($select->options) - 1];
            if ($release !== GrammarRelease::MySql5744 || $last === SelectOption::Cache || $last === SelectOption::NoCache) {
                $this->misplaced($select, $first, $release);
            }
            if (in_array(SelectOption::All, $select->options, true) && in_array(SelectOption::Distinct, $select->options, true)) {
                throw StatementError::WrongUsage->error('ALL', 'DISTINCT');
            }
            $this->misplaced($select, $first, $release);
            foreach ($select === $first || $program ? [] : [SelectOption::HighPriority, SelectOption::BufferResult, SelectOption::CalcFoundRows] as $option) {
                if (in_array($option, $select->options, true)) {
                    throw StatementError::CantUseOptionHere->error($option->value);
                }
            }
            if ($after !== null) {
                $after($select, false);
            }
        }
        $this->closed($open, null, $after);
    }

    /**
     * Ends the open query blocks that do not hold the next block, innermost first, and answers those still open.
     *
     * @param list<Select> $open The blocks begun and not ended yet, outermost first
     * @param Select|null $next The block written next, or null at the end of the statement
     * @param Closure(Select, bool): void|null $after The check of each block that ends
     * @return list<Select>
     */
    public function closed(array $open, ?Select $next, ?Closure $after): array
    {
        while ($open !== [] && ($next === null || !in_array($next, (new Walker())->find($open[count($open) - 1], Select::class), true))) {
            $ended = array_pop($open);
            if ($after !== null) {
                $after($ended, true);
            }
        }

        return $open;
    }

    /**
     * Answers the statement whose first query block may carry the options of a statement: in MySQL 5.6 and 5.7 the query of a view and the statement EXPLAIN explains (verified on live 5.6.51 and 5.7.44 servers), else the statement.
     */
    public function carrier(Node $statement, GrammarRelease $release): Node
    {
        if ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
            return $statement;
        }

        return match (true) {
            $statement instanceof CreateView || $statement instanceof AlterView => $statement->definition->query,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain => $this->carrier($statement->statement, $release),
            default => $statement,
        };
    }

    /**
     * Raises the error of a query cache modifier MySQL 5.6 or 5.7 refuses where it is written: in any block but the first of the statement.
     *
     * The error names the first modifier of the block in 5.6 and the last in 5.7.
     *
     * @throws \MySqlMemory\Error\SqlError When a block has such a modifier
     */
    public function cached(Node $statement, GrammarRelease $release): void
    {
        $first = $this->first($statement);
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            $this->misplaced($select, $first, $release);
        }
    }

    /**
     * Raises the error of a query cache modifier MySQL 5.6 or 5.7 refuses in one query block, which is not the first of the statement.
     *
     * The server refuses it after it checked the blocks written before; in the block, MySQL 5.6
     * refuses it before it checks that ALL and DISTINCT exclude each other, and 5.7 only when a
     * query cache modifier is the last modifier of the block, else after (verified on live 5.6.51
     * and 5.7.44 servers).
     *
     * @throws \MySqlMemory\Error\SqlError When the block has such a modifier
     */
    public function misplaced(Select $select, ?Select $first, GrammarRelease $release): void
    {
        if (($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) || $select === $first) {
            return;
        }
        $options = array_values(array_filter($select->options, static fn (SelectOption $option): bool => $option === SelectOption::Cache || $option === SelectOption::NoCache));
        if ($options !== []) {
            throw StatementError::CantUseOptionHere->error(($release === GrammarRelease::MySql5651 ? $options[0] : $options[count($options) - 1])->value);
        }
    }

    /**
     * Raises the error of an INTO that MySQL 5.6 or 5.7 refuses in a union operand other than the last one, written in parentheses (ER_WRONG_USAGE).
     *
     * @throws \MySqlMemory\Error\SqlError When such an operand writes INTO
     */
    public function united(Node $statement, GrammarRelease $release): void
    {
        if ($this->boundaries($statement, $release) !== []) {
            throw StatementError::WrongUsage->error('UNION', 'INTO');
        }
    }

    /**
     * Answers the object ids of the query blocks before which MySQL 5.6 or 5.7 refuses an INTO in a union operand but the last: the first block of the operands after it.
     *
     * The server refuses the INTO when it reads the UNION after it, so the blocks written before are
     * checked first (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @return list<int>
     */
    public function boundaries(Node $statement, GrammarRelease $release): array
    {
        if ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
            return [];
        }
        $boundaries = [];
        foreach ([...(new Walker())->find($statement, SetOperation::class), ...(new Walker())->find($statement, OrderedSetOperation::class), ...(new Walker())->find($statement, LeadingUnion::class)] as $union) {
            $right = $this->blocks($union->right);
            if ($right !== [] && array_filter($this->blocks($union->left), static fn (Select $block): bool => $block->into !== null) !== []) {
                $boundaries[] = spl_object_id($right[0]);
            }
        }

        return $boundaries;
    }

    /**
     * Answers the query blocks a query is made of, through parentheses, WITH and set operations.
     *
     * @return list<Select>
     */
    public function blocks(Node $query): array
    {
        return match (true) {
            $query instanceof Select => [$query],
            $query instanceof ParenthesizedQuery => $this->blocks($query->query),
            $query instanceof QueryExpression => $this->blocks($query->body),
            $query instanceof SetOperation, $query instanceof OrderedSetOperation, $query instanceof LeadingUnion => [...$this->blocks($query->left), ...$this->blocks($query->right)],
            default => [],
        };
    }

    /**
     * Answers the query block that may carry the options of the statement: the first block of its query, or of the query an INSERT or a CREATE TABLE writes.
     */
    public function first(Node $statement): ?Select
    {
        $query = match (true) {
            $statement instanceof InsertQuery => $statement->source,
            $statement instanceof CreateTable => $statement->query?->query,
            default => $statement,
        };
        while ($query instanceof QueryStatement || $query instanceof ParenthesizedQuery || $query instanceof QueryExpression || $query instanceof SetOperation || $query instanceof OrderedSetOperation || $query instanceof LeadingUnion) {
            $query = match (true) {
                $query instanceof QueryStatement, $query instanceof ParenthesizedQuery => $query->query,
                $query instanceof QueryExpression => $query->body,
                default => $query->left,
            };
        }

        return $query instanceof Select ? $query : null;
    }
}

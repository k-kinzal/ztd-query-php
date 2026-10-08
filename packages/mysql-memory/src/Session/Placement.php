<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

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
 * cache modifier in any of those other blocks, and INTO in a union operand but the last
 * (verified on live 5.6.51 and 5.7.44 servers).
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
     * @throws \MySqlMemory\Error\SqlError When a block has such options
     */
    public function check(Node $statement, GrammarRelease $release = GrammarRelease::MySql847): void
    {
        $this->cached($statement, $release);
        $this->united($statement, $release);
        $first = $this->first($statement);
        $program = \MySqlMemory\Command\Dispatcher::program($statement);
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            if (in_array(SelectOption::All, $select->options, true) && in_array(SelectOption::Distinct, $select->options, true)) {
                throw StatementError::WrongUsage->error('ALL', 'DISTINCT');
            }
            if ($select === $first || $program) {
                continue;
            }
            foreach ([SelectOption::HighPriority, SelectOption::BufferResult, SelectOption::CalcFoundRows] as $option) {
                if (in_array($option, $select->options, true)) {
                    throw StatementError::CantUseOptionHere->error($option->value);
                }
            }
        }
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
        if ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
            return;
        }
        $first = $this->first($statement);
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            $options = array_values(array_filter($select->options, static fn (SelectOption $option): bool => $option === SelectOption::Cache || $option === SelectOption::NoCache));
            if ($options !== [] && $select !== $first) {
                throw StatementError::CantUseOptionHere->error(($release === GrammarRelease::MySql5651 ? $options[0] : $options[count($options) - 1])->value);
            }
        }
    }

    /**
     * Raises the error of an INTO that MySQL 5.6 or 5.7 refuses in a union operand other than the last one, written in parentheses (ER_WRONG_USAGE).
     *
     * @throws \MySqlMemory\Error\SqlError When such an operand writes INTO
     */
    public function united(Node $statement, GrammarRelease $release): void
    {
        if ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
            return;
        }
        foreach ([...(new Walker())->find($statement, SetOperation::class), ...(new Walker())->find($statement, OrderedSetOperation::class), ...(new Walker())->find($statement, LeadingUnion::class)] as $union) {
            foreach ($this->blocks($union->left) as $block) {
                if ($block->into !== null) {
                    throw StatementError::WrongUsage->error('UNION', 'INTO');
                }
            }
        }
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

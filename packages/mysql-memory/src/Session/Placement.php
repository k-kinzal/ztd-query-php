<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Walker;
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
 * order refused (ER_CANT_USE_OPTION_HERE). The blocks are checked in written order, each before
 * the blocks written in it (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
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
    public function check(Node $statement): void
    {
        $first = $this->first($statement);
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            if (in_array(SelectOption::All, $select->options, true) && in_array(SelectOption::Distinct, $select->options, true)) {
                throw StatementError::WrongUsage->error('ALL', 'DISTINCT');
            }
            if ($select === $first) {
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

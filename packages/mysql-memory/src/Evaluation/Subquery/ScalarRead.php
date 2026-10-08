<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A scalar subquery: the value of its one row, NULL for no row, an error for more than one (ER_SUBQUERY_NO_1_ROW).
 *
 * A subquery that does not depend on the row is run once for the statement, the first time its
 * value is needed, unless the server reads it as the expression of its one row: a SELECT
 * without a table, an aggregate or HAVING (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory
 */
final class ScalarRead implements Evaluable
{
    /**
     * @param Rows $rows The rows of the subquery
     * @param Domain $domain The domain of its column
     * @param bool $once Whether the subquery is run once for the statement
     */
    public function __construct(public readonly Rows $rows, public readonly Domain $domain, public readonly bool $once = false)
    {
    }

    /**
     * Answers the domain of the value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Computes the value for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        if (!$this->once) {
            return $this->read($frame);
        }
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = [$this->read($frame)];
        }

        return $kept[$this][0];
    }

    /**
     * Runs the subquery and answers the value of its one row.
     *
     * @throws \MySqlMemory\Error\SqlError When the subquery answers more than one row
     */
    public function read(Frame $frame): int|float|string|null
    {
        $iterator = $this->rows->start($frame);
        $row = $iterator->read();
        if ($row === null) {
            return null;
        }
        if ($iterator->read() !== null) {
            throw ErrorCode::SubqueryNotOneRow->error();
        }

        return $row[0];
    }
}

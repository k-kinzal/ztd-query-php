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
 * @visibility MySqlMemory
 */
final class ScalarRead implements Evaluable
{
    /**
     * @param Rows $rows The rows of the subquery
     * @param Domain $domain The domain of its column
     */
    public function __construct(public readonly Rows $rows, public readonly Domain $domain)
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

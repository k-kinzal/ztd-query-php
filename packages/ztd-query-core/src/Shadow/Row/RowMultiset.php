<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Row;

use ZtdQuery\Connection\StatementInterface;

/**
 * Rows counted with their repeats: what one set of rows has that another does not.
 *
 * A table may hold the same row twice, so comparing two states of it cannot be
 * done with set arithmetic — a row that appears three times before and once
 * after has been removed twice. Every row here is paired off against at most
 * one row on the other side, and what stays unpaired is the difference.
 *
 * Column order is not part of a row's identity: the same columns carrying the
 * same values are the same row however the reader happened to order them.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class RowMultiset
{
    /**
     * @param RowMatch $match Decides when two rows are the same row
     */
    public function __construct(private readonly RowMatch $match = new RowMatch())
    {
    }

    /**
     * Answers the rows on the left that nothing on the right pairs with.
     *
     * @param list<Row> $left Rows to account for
     * @param list<Row> $right Rows they are paired off against
     *
     * @return list<Row> The unpaired rows, in the order the left held them
     */
    public function difference(array $left, array $right): array
    {
        $remaining = $right;
        $difference = [];
        foreach ($left as $row) {
            $paired = null;
            foreach ($remaining as $index => $candidate) {
                if ($this->match->sameRow($row, $candidate)) {
                    $paired = $index;
                    break;
                }
            }
            if ($paired === null) {
                $difference[] = $row;
                continue;
            }
            unset($remaining[$paired]);
        }

        return $difference;
    }
}

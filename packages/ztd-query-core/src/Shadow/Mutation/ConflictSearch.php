<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\CandidateKeyConflict;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\TableDefinition;

/**
 * Finds the row an incoming row would collide with, where a clause narrows which.
 *
 * A partial index and a conflict target both say that only some rows take part
 * in a collision. Where a statement names one, a row that does not satisfy it
 * collides with nothing, and neither do the rows already there that do not.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class ConflictSearch
{
    /**
     * @param CandidateKeySet $candidateKeys Keys a collision could be on
     * @param UpsertExpression|null $predicate What narrows which rows take part, or null when nothing does
     * @param string $tableName Table being written to
     */
    public function __construct(
        private readonly CandidateKeySet $candidateKeys,
        private readonly ?UpsertExpression $predicate,
        private readonly string $tableName,
    ) {
    }

    /**
     * Answers the row an incoming row would collide with, if any.
     *
     * @param Row $row Row that would be written
     * @param list<Row> $existingRows Rows already there
     *
     * @return CandidateKeyConflict|null Where and on which key it collides, or null when it does not
     *
     * @throws UnsupportedSqlException When the narrowing clause cannot be worked out for a row
     */
    public function of(array $row, array $existingRows): ?CandidateKeyConflict
    {
        if ($this->predicate === null) {
            return $this->candidateKeys->findConflict($row, $existingRows);
        }
        if (!$this->predicate->matches($row, $row, $this->tableName)) {
            return null;
        }

        $eligibleRows = [];
        foreach ($existingRows as $index => $existingRow) {
            if ($this->predicate->matches($existingRow, $existingRow, $this->tableName)) {
                $eligibleRows[$index] = $existingRow;
            }
        }

        return $this->candidateKeys->findConflict($row, $eligibleRows);
    }
}

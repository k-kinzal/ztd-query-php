<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinition;

/**
 * What an UPSERT does to a row it found a conflict on.
 *
 * Two questions have to be answered about every conflict: whether the
 * statement goes on to update the row at all, and what the row looks like
 * once it has. Both depend on where the assignment was worked out — the
 * database can evaluate an expression against the row as it stood, but not
 * against a row this same statement changed a moment ago, and then the
 * expression has to be read here or the statement refused.
 *
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type RowValue from TableDefinition
 */
final class UpsertUpdate
{
    /**
     * @param string $tableName Table being written to
     * @param array<int, string> $primaryKeys Columns a conflict is found on
     * @param array<int, string> $updateColumns Columns the statement assigns to
     * @param array<string, UpsertExpression|null> $updateValues What each column is assigned, as ZTD reads it
     * @param array<string, string> $updateSqlValues What each column is assigned, as the statement wrote it
     * @param string|null $updateSqlPredicate Condition on the update, as the statement wrote it
     * @param UpsertExpression|null $updatePredicate Condition on the update, as ZTD reads it
     * @param bool $databaseEvaluated Whether the database worked the assignments out already
     * @param UpsertMutationRow $codec Reads what the database worked out off a row
     */
    public function __construct(
        private readonly string $tableName,
        private readonly array $primaryKeys,
        private readonly array $updateColumns,
        private readonly array $updateValues,
        private readonly array $updateSqlValues,
        private readonly ?string $updateSqlPredicate,
        private readonly ?UpsertExpression $updatePredicate,
        private readonly bool $databaseEvaluated,
        private readonly UpsertMutationRow $codec = new UpsertMutationRow(),
    ) {
    }

    /**
     * Answers the row the statement was trying to write.
     *
     * @param Row $row Row as it arrived, carrying what the database worked out
     *
     * @return Row The row the statement wrote, without any of that
     */
    public function incomingRow(array $row): array
    {
        return $this->databaseEvaluated
            ? $this->codec->incomingRow($row, count($this->updateColumns))
            : $row;
    }

    /**
     * Reports whether the statement goes on to update the row it conflicted with.
     *
     * @param Row $row Row as it arrived, carrying what the database worked out
     * @param Row $existingRow Row the conflict was found on
     * @param Row $incomingRow Row the statement was trying to write
     * @param bool $changedEarlier Whether this same statement already changed the row
     *
     * @return bool True when the row is one the statement updates
     *
     * @throws UnsupportedSqlException When the condition has to be read here and cannot be
     */
    public function applies(array $row, array $existingRow, array $incomingRow, bool $changedEarlier): bool
    {
        if (!$this->databaseEvaluated) {
            return $this->updatePredicate === null
                || $this->updatePredicate->matches($existingRow, $incomingRow, $this->tableName);
        }
        if ($changedEarlier && $this->updateSqlPredicate !== null) {
            return $this->predicateRead('Sequential UPSERT predicate')
                ->matches($existingRow, $incomingRow, $this->tableName);
        }
        if (array_key_exists($this->codec->predicateColumn(), $row)) {
            return $this->codec->predicateMatches($row[$this->codec->predicateColumn()]);
        }
        if ($this->updateSqlPredicate !== null) {
            return $this->predicateRead('UPSERT predicate requires local evaluation')
                ->matches($existingRow, $incomingRow, $this->tableName);
        }

        return true;
    }

    /**
     * Answers the row as the statement leaves it.
     *
     * A statement that names no column to assign carries everything it was
     * writing over, except what the conflict was found on, which is what
     * makes it the same row.
     *
     * @param Row $row Row as it arrived, carrying what the database worked out
     * @param Row $existingRow Row the conflict was found on
     * @param Row $incomingRow Row the statement was trying to write
     * @param bool $changedEarlier Whether this same statement already changed the row
     *
     * @return Row The updated row
     *
     * @throws UnsupportedSqlException When an assignment has to be read here and cannot be
     */
    public function of(array $row, array $existingRow, array $incomingRow, bool $changedEarlier): array
    {
        foreach ($this->updateColumns as $index => $column) {
            $existingRow = $this->withColumn($existingRow, $column, $index, $row, $incomingRow, $changedEarlier);
        }
        if ($this->updateColumns !== []) {
            return $existingRow;
        }
        foreach ($incomingRow as $column => $value) {
            if (!in_array($column, $this->primaryKeys, true)) {
                $existingRow[$column] = $value;
            }
        }

        return $existingRow;
    }

    /**
     * Answers the row with one column assigned as the statement asked.
     *
     * A column the database already worked out is taken from what it worked
     * out; a column it could not is read here, against the row as this
     * statement has left it so far, which is what makes assignments written
     * over one another read in the order they were written.
     *
     * @param Row $updatedRow Row as the statement has left it so far
     * @param string $column Column being assigned
     * @param int $index Which of the assigned columns this is
     * @param Row $row Row as it arrived, carrying what the database worked out
     * @param Row $incomingRow Row the statement was trying to write
     * @param bool $changedEarlier Whether this same statement already changed the row
     *
     * @return Row The row with that column assigned
     *
     * @throws UnsupportedSqlException When the assignment has to be read here and cannot be
     */
    public function withColumn(
        array $updatedRow,
        string $column,
        int $index,
        array $row,
        array $incomingRow,
        bool $changedEarlier,
    ): array {
        if (!$this->databaseEvaluated) {
            if (isset($this->updateValues[$column])) {
                $updatedRow[$column] = $this->updateValues[$column]
                    ->evaluate($updatedRow, $incomingRow, $this->tableName);
            } elseif (isset($incomingRow[$column])) {
                $updatedRow[$column] = $incomingRow[$column];
            }

            return $updatedRow;
        }

        $worked = $this->codec->valueColumn($index);
        if ($changedEarlier && isset($this->updateSqlValues[$column])) {
            $updatedRow[$column] = $this->valueRead($column, 'Sequential UPSERT expression')
                ->evaluate($updatedRow, $incomingRow, $this->tableName);
        } elseif (array_key_exists($worked, $row)) {
            $updatedRow[$column] = $row[$worked];
        } elseif (isset($this->updateSqlValues[$column])) {
            $updatedRow[$column] = $this->valueRead($column, 'UPSERT expression requires local evaluation')
                ->evaluate($updatedRow, $incomingRow, $this->tableName);
        }

        return $updatedRow;
    }

    /**
     * Answers the condition as ZTD reads it, insisting it could be read.
     *
     * @param string $reason Why it has to be read here rather than by the database
     *
     * @return UpsertExpression The condition
     *
     * @throws UnsupportedSqlException When the statement wrote a condition ZTD cannot read
     */
    public function predicateRead(string $reason): UpsertExpression
    {
        if ($this->updatePredicate === null) {
            throw new UnsupportedSqlException($this->updateSqlPredicate ?? '', $reason);
        }

        return $this->updatePredicate;
    }

    /**
     * Answers what a column is assigned as ZTD reads it, insisting it could be read.
     *
     * @param string $column Column being assigned
     * @param string $reason Why it has to be read here rather than by the database
     *
     * @return UpsertExpression The assignment
     *
     * @throws UnsupportedSqlException When the statement wrote an assignment ZTD cannot read
     */
    public function valueRead(string $column, string $reason): UpsertExpression
    {
        $value = $this->updateValues[$column] ?? null;
        if ($value === null) {
            throw new UnsupportedSqlException($this->updateSqlValues[$column] ?? '', $reason);
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\Key\CandidateKeySet;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies INSERT ... ON DUPLICATE KEY UPDATE (UPSERT) to the shadow store.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class UpsertMutation implements DataMutation
{
    /**
     * Target table to upsert into.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Primary key columns for duplicate detection.
     *
     * @var array<int, string>
     */
    private array $primaryKeys;

    /**
     * Columns to update on duplicate.
     *
     * @var array<int, string>
     */
    private array $updateColumns;

    /**
     * Parsed values to use for update on duplicate.
     *
     * @var array<string, UpsertExpression|null>
     */
    private array $updateValues;

    private CandidateKeySet $candidateKeys;

    private ?UpsertExpression $updatePredicate;

    private ?UpsertExpression $conflictPredicate;

    private bool $databaseEvaluated;

    /** @var array<string, string> */
    private array $updateSqlValues;

    private ?string $updateSqlPredicate;

    /** @var list<Row> */
    private array $resultRows = [];

    private ConflictSearch $conflicts;

    private UpsertUpdate $update;

    /**
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     * @param array<int, string> $updateColumns Columns to update on duplicate.
     * @param array<string, UpsertExpression|null> $updateValues Values to use for update on duplicate.
     * @param CandidateKeySet|null $candidateKeys Candidate keys used for conflict detection.
     * @param UpsertExpression|null $updatePredicate Condition that controls the conflict update.
     * @param array<string, string> $updateSqlValues Raw expressions used only for database-evaluation diagnostics.
     */
    public function __construct(
        string $tableName,
        array $primaryKeys,
        array $updateColumns = [],
        array $updateValues = [],
        ?CandidateKeySet $candidateKeys = null,
        ?UpsertExpression $updatePredicate = null,
        bool $databaseEvaluated = false,
        array $updateSqlValues = [],
        ?string $updateSqlPredicate = null,
        ?UpsertExpression $conflictPredicate = null,
    ) {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
        $this->updateColumns = $updateColumns;
        $this->updateValues = $updateValues;
        $this->updateSqlValues = $updateSqlValues;
        $this->updateSqlPredicate = $updateSqlPredicate;
        $this->candidateKeys = $candidateKeys ?? CandidateKeySet::fromSchema($primaryKeys);
        $this->updatePredicate = $updatePredicate;
        $this->databaseEvaluated = $databaseEvaluated;
        $this->conflictPredicate = $conflictPredicate;
        $this->conflicts = new ConflictSearch($this->candidateKeys, $this->conflictPredicate, $this->tableName);
        $this->update = new UpsertUpdate(
            $this->tableName,
            $this->primaryKeys,
            $this->updateColumns,
            $this->updateValues,
            $this->updateSqlValues,
            $this->updateSqlPredicate,
            $this->updatePredicate,
            $this->databaseEvaluated,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When an assignment is written in a way ZTD cannot work out
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);
        $changedRows = [];
        $this->resultRows = [];
        foreach ($rows as $row) {
            $incomingRow = $this->update->incomingRow($row);
            $conflict = $this->conflicts->of($incomingRow, $existingRows);
            if ($conflict === null) {
                $existingRows[] = $incomingRow;
                $changedRows[array_key_last($existingRows)] = true;
                $this->resultRows[] = $incomingRow;
                continue;
            }

            $index = $conflict->rowIndex;
            $changedEarlier = ($changedRows[$index] ?? false) === true;
            if (!$this->update->applies($row, $existingRows[$index], $incomingRow, $changedEarlier)) {
                continue;
            }

            $existingRows[$index] = $this->update->of($row, $existingRows[$index], $incomingRow, $changedEarlier);
            $changedRows[$index] = true;
            $this->resultRows[] = $existingRows[$index];
        }

        $store->set($this->tableName, $existingRows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return list<Row>
     */
    public function resultRows(): array
    {
        return $this->resultRows;
    }
}

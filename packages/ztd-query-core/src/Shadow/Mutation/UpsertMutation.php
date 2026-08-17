<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies INSERT ... ON DUPLICATE KEY UPDATE (UPSERT) to the shadow store.
 */
final class UpsertMutation implements ShadowMutation
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
     * @var array<string, UpsertExpression>
     */
    private array $updateValues;

    private CandidateKeySet $candidateKeys;

    private ?UpsertExpression $updatePredicate;

    /** @var array<int, array<string, mixed>> */
    private array $resultRows = [];

    /**
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     * @param array<int, string> $updateColumns Columns to update on duplicate.
     * @param array<string, UpsertExpression> $updateValues Values to use for update on duplicate.
     * @param CandidateKeySet|null $candidateKeys Candidate keys used for conflict detection.
     * @param UpsertExpression|null $updatePredicate Condition that controls the conflict update.
     */
    public function __construct(
        string $tableName,
        array $primaryKeys,
        array $updateColumns = [],
        array $updateValues = [],
        ?CandidateKeySet $candidateKeys = null,
        ?UpsertExpression $updatePredicate = null,
    ) {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
        $this->updateColumns = $updateColumns;
        $this->updateValues = $updateValues;
        $this->candidateKeys = $candidateKeys ?? CandidateKeySet::fromSchema($primaryKeys);
        $this->updatePredicate = $updatePredicate;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);
        $insertRows = [];
        $this->resultRows = [];
        foreach ($rows as $row) {
            $conflict = $this->candidateKeys->findConflict($row, $existingRows);
            if ($conflict !== null) {
                $existingIndex = $conflict->rowIndex;
                $updatedRow = $existingRows[$existingIndex];
                if ($this->updatePredicate !== null
                    && !$this->updatePredicate->matches($updatedRow, $row, $this->tableName)
                ) {
                    continue;
                }
                foreach ($this->updateColumns as $col) {
                    if (isset($this->updateValues[$col])) {
                        $updatedRow[$col] = $this->updateValues[$col]->evaluate($updatedRow, $row, $this->tableName);
                    } elseif (isset($row[$col])) {
                        $updatedRow[$col] = $row[$col];
                    }
                }
                if ($this->updateColumns === []) {
                    foreach ($row as $col => $value) {
                        if (!in_array($col, $this->primaryKeys, true)) {
                            $updatedRow[$col] = $value;
                        }
                    }
                }
                $existingRows[$existingIndex] = $updatedRow;
                $this->resultRows[] = $updatedRow;
            } else {
                $insertRows[] = $row;
                $this->resultRows[] = $row;
            }
        }

        $store->set($this->tableName, $existingRows);
        if ($insertRows !== []) {
            $store->insert($this->tableName, $insertRows);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /** @return array<int, array<string, mixed>> */
    public function resultRows(): array
    {
        return $this->resultRows;
    }

}

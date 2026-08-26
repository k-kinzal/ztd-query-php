<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies INSERT ... ON DUPLICATE KEY UPDATE (UPSERT) to the shadow store.
 *
 * @phpstan-import-type Row from StatementInterface
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
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);
        $changedRows = [];
        $this->resultRows = [];
        $codec = new UpsertMutationRow();
        foreach ($rows as $row) {
            $incomingRow = $this->databaseEvaluated
                ? $codec->incomingRow($row, count($this->updateColumns))
                : $row;
            $conflict = $this->findConflict($incomingRow, $existingRows);
            if ($conflict !== null) {
                $existingIndex = $conflict->rowIndex;
                $updatedRow = $existingRows[$existingIndex];
                $requiresLocalEvaluation = ($changedRows[$existingIndex] ?? false) === true;
                if ($this->databaseEvaluated) {
                    if ($requiresLocalEvaluation && $this->updateSqlPredicate !== null) {
                        if ($this->updatePredicate === null) {
                            throw new UnsupportedSqlException(
                                $this->updateSqlPredicate,
                                'Sequential UPSERT predicate',
                            );
                        }
                        if (!$this->updatePredicate->matches($updatedRow, $incomingRow, $this->tableName)) {
                            continue;
                        }
                    } elseif (array_key_exists($codec->predicateColumn(), $row)) {
                        if (!$codec->predicateMatches($row[$codec->predicateColumn()])) {
                            continue;
                        }
                    } elseif ($this->updateSqlPredicate !== null) {
                        if ($this->updatePredicate === null) {
                            throw new UnsupportedSqlException(
                                $this->updateSqlPredicate,
                                'UPSERT predicate requires local evaluation',
                            );
                        }
                        if (!$this->updatePredicate->matches($updatedRow, $incomingRow, $this->tableName)) {
                            continue;
                        }
                    }
                } elseif ($this->updatePredicate !== null) {
                    if (!$this->updatePredicate->matches($updatedRow, $incomingRow, $this->tableName)) {
                        continue;
                    }
                }
                foreach ($this->updateColumns as $index => $col) {
                    if ($this->databaseEvaluated) {
                        $metadata = $codec->valueColumn($index);
                        if ($requiresLocalEvaluation && isset($this->updateSqlValues[$col])) {
                            if (!isset($this->updateValues[$col])) {
                                throw new UnsupportedSqlException(
                                    $this->updateSqlValues[$col],
                                    'Sequential UPSERT expression',
                                );
                            }
                            $updatedRow[$col] = $this->updateValues[$col]->evaluate(
                                $updatedRow,
                                $incomingRow,
                                $this->tableName,
                            );
                        } elseif (array_key_exists($metadata, $row)) {
                            $updatedRow[$col] = $row[$metadata];
                        } elseif (isset($this->updateSqlValues[$col])) {
                            if (!isset($this->updateValues[$col])) {
                                throw new UnsupportedSqlException(
                                    $this->updateSqlValues[$col],
                                    'UPSERT expression requires local evaluation',
                                );
                            }
                            $updatedRow[$col] = $this->updateValues[$col]->evaluate(
                                $updatedRow,
                                $incomingRow,
                                $this->tableName,
                            );
                        }
                    } elseif (isset($this->updateValues[$col])) {
                        $updatedRow[$col] = $this->updateValues[$col]->evaluate($updatedRow, $incomingRow, $this->tableName);
                    } elseif (isset($incomingRow[$col])) {
                        $updatedRow[$col] = $incomingRow[$col];
                    }
                }
                if ($this->updateColumns === []) {
                    foreach ($incomingRow as $col => $value) {
                        if (!in_array($col, $this->primaryKeys, true)) {
                            $updatedRow[$col] = $value;
                        }
                    }
                }
                $existingRows[$existingIndex] = $updatedRow;
                $changedRows[$existingIndex] = true;
                $this->resultRows[] = $updatedRow;
            } else {
                $existingRows[] = $incomingRow;
                $changedRows[array_key_last($existingRows)] = true;
                $this->resultRows[] = $incomingRow;
            }
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
    /**
     * @param Row $incomingRow
     * @param list<Row> $existingRows
     */
    private function findConflict(array $incomingRow, array $existingRows): ?\ZtdQuery\Schema\CandidateKeyConflict
    {
        if ($this->conflictPredicate === null) {
            return $this->candidateKeys->findConflict($incomingRow, $existingRows);
        }
        if (!$this->conflictPredicate->matches($incomingRow, $incomingRow, $this->tableName)) {
            return null;
        }

        $eligibleRows = [];
        foreach ($existingRows as $index => $existingRow) {
            if ($this->conflictPredicate->matches($existingRow, $existingRow, $this->tableName)) {
                $eligibleRows[$index] = $existingRow;
            }
        }

        return $this->candidateKeys->findConflict($incomingRow, $eligibleRows);
    }
}

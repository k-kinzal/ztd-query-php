<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies REPLACE INTO operation to the shadow store.
 * REPLACE deletes the existing row and inserts the new one.
 */
final class ReplaceMutation implements ShadowMutation
{
    /**
     * Target table to replace into.
     *
     * @var string
     */
    private string $tableName;

    private CandidateKeySet $candidateKeys;

    /**
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     * @param CandidateKeySet|null $candidateKeys Candidate keys used for conflict detection.
     */
    public function __construct(string $tableName, array $primaryKeys = [], ?CandidateKeySet $candidateKeys = null)
    {
        $this->tableName = $tableName;
        $this->candidateKeys = $candidateKeys ?? CandidateKeySet::fromSchema($primaryKeys);
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);

        foreach ($rows as $row) {
            while (($conflict = $this->candidateKeys->findConflict($row, $existingRows)) !== null) {
                unset($existingRows[$conflict->rowIndex]);
                $existingRows = array_values($existingRows);
            }
        }

        $existingRows = array_values($existingRows);
        $store->set($this->tableName, $existingRows);
        $store->insert($this->tableName, $rows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}

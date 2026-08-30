<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies INSERT result rows to the shadow store.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class InsertMutation implements DataMutation
{
    /**
     * Target table to insert into.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Whether to ignore duplicate key errors.
     *
     * @var bool
     */
    private bool $ignore;

    /**
     * Table definition for constraint validation.
     *
     * @var TableDefinition|null
     */
    private ?TableDefinition $tableDefinition;

    /**
     * Original SQL statement for exception messages.
     *
     * @var string
     */
    private string $sql;

    /**
     * Whether constraint validation is enabled.
     *
     * @var bool
     */
    private bool $validateConstraints;

    private CandidateKeySet $candidateKeys;

    private ?UpsertExpression $conflictPredicate;

    private RowConstraints $constraints;

    private ConflictSearch $conflicts;

    /**
     * @param string $tableName Target table.
     * @param array<int, string> $primaryKeys Primary key columns.
     * @param bool $ignore Whether to ignore duplicates (INSERT IGNORE).
     * @param TableDefinition|null $tableDefinition Table definition for constraint validation.
     * @param string $sql Original SQL statement for exception messages.
     * @param bool $validateConstraints Whether to validate constraints.
     * @param CandidateKeySet|null $candidateKeys Candidate keys used for duplicate detection.
     * @param UpsertExpression|null $conflictPredicate Condition that controls candidate-key eligibility.
     */
    public function __construct(
        string $tableName,
        array $primaryKeys = [],
        bool $ignore = false,
        ?TableDefinition $tableDefinition = null,
        string $sql = '',
        bool $validateConstraints = false,
        ?CandidateKeySet $candidateKeys = null,
        ?UpsertExpression $conflictPredicate = null,
    ) {
        $this->tableName = $tableName;
        $this->ignore = $ignore;
        $this->tableDefinition = $tableDefinition;
        $this->sql = $sql;
        $this->validateConstraints = $validateConstraints;
        $this->candidateKeys = $candidateKeys ?? CandidateKeySet::fromSchema($primaryKeys);
        $this->conflictPredicate = $conflictPredicate;
        $this->constraints = new RowConstraints($this->tableDefinition, $this->tableName, $this->sql);
        $this->conflicts = new ConflictSearch($this->candidateKeys, $this->conflictPredicate, $this->tableName);
    }

    /**
     * {@inheritDoc}
     *
     * @throws DuplicateKeyException When a row would collide with one already there on a candidate key
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $existingRows = $store->get($this->tableName);
        $filteredRows = [];

        foreach ($rows as $row) {
            if ($this->validateConstraints && $this->tableDefinition !== null) {
                $this->constraints->assertNoNullWhereNoneIsAllowed($row);
            }

            $conflict = $this->conflicts->of($row, $existingRows);
            if ($conflict !== null) {
                if ($this->ignore) {
                    continue;
                }

                if ($this->validateConstraints) {
                    throw new DuplicateKeyException(
                        $this->sql,
                        $this->tableName,
                        $conflict->keyName,
                        $conflict->values
                    );
                }
            }

            if ($this->validateConstraints && $this->tableDefinition !== null) {
                $this->constraints->assertNoDuplicateUniqueKey($row, $existingRows);
            }

            $filteredRows[] = $row;
            $existingRows[] = $row;
        }

        $store->insert($this->tableName, $filteredRows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }




}

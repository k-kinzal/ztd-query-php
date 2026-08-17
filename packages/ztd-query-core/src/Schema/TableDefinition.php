<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Structured representation of a table's schema metadata.
 */
final class TableDefinition
{
    /**
     * @var array<string, ColumnType>
     */
    public readonly array $typedColumns;

    /** @var array<string, string> */
    public readonly array $columnDefaults;

    /** @var array<string, IdentityGenerationStrategy> */
    public readonly array $identityStrategies;

    /** @var array<string, string> */
    public readonly array $generatedExpressions;

    /** @var array<string, ForeignKeyDefinition> */
    public readonly array $foreignKeys;

    public readonly ?TablePartitioning $partitioning;

    public readonly ?TablePartitionKey $partitionKey;

    public readonly ?TablePartitionRelation $partitionRelation;

    /**
     * @param array<int, string> $columns Column names in declaration order.
     * @param array<string, string> $columnTypes Column name => MySQL type string.
     * @param array<int, string> $primaryKeys Primary key column names.
     * @param array<int, string> $notNullColumns Columns with NOT NULL constraint.
     * @param array<string, array<int, string>> $uniqueConstraints Key name => column list.
     * @param array<string, ColumnType> $typedColumns Column name => structured ColumnType.
     * @param array<string, string> $columnDefaults Column name => SQL default expression.
     * @param array<string, IdentityGenerationStrategy> $identityStrategies Column name => shadow generation strategy.
     * @param array<string, string> $generatedExpressions Column name => database generated expression.
     * @param array<string, ForeignKeyDefinition> $foreignKeys Constraint name => foreign-key definition.
     * @param TablePartitioning|null $partitioning Named partition selection predicates.
     * @param TablePartitionKey|null $partitionKey Declarative partition key metadata.
     * @param TablePartitionRelation|null $partitionRelation Parent partition relationship.
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $columnTypes,
        public readonly array $primaryKeys,
        public readonly array $notNullColumns,
        public readonly array $uniqueConstraints,
        array $typedColumns = [],
        array $columnDefaults = [],
        array $identityStrategies = [],
        array $generatedExpressions = [],
        array $foreignKeys = [],
        ?TablePartitioning $partitioning = null,
        ?TablePartitionKey $partitionKey = null,
        ?TablePartitionRelation $partitionRelation = null,
    ) {
        $this->typedColumns = $typedColumns;
        $this->columnDefaults = $columnDefaults;
        $this->identityStrategies = $identityStrategies;
        $this->generatedExpressions = $generatedExpressions;
        $this->foreignKeys = $foreignKeys;
        $this->partitioning = $partitioning;
        $this->partitionKey = $partitionKey;
        $this->partitionRelation = $partitionRelation;
    }

    public function candidateKeys(): CandidateKeySet
    {
        return CandidateKeySet::fromSchema($this->primaryKeys, $this->uniqueConstraints);
    }

    public function withPartitioning(?TablePartitioning $partitioning): self
    {
        return new self(
            $this->columns,
            $this->columnTypes,
            $this->primaryKeys,
            $this->notNullColumns,
            $this->uniqueConstraints,
            $this->typedColumns,
            $this->columnDefaults,
            $this->identityStrategies,
            $this->generatedExpressions,
            $this->foreignKeys,
            $partitioning,
            $this->partitionKey,
            $this->partitionRelation,
        );
    }

    public function withPartitionKey(?TablePartitionKey $partitionKey): self
    {
        return new self(
            $this->columns,
            $this->columnTypes,
            $this->primaryKeys,
            $this->notNullColumns,
            $this->uniqueConstraints,
            $this->typedColumns,
            $this->columnDefaults,
            $this->identityStrategies,
            $this->generatedExpressions,
            $this->foreignKeys,
            $this->partitioning,
            $partitionKey,
            $this->partitionRelation,
        );
    }

    public function withPartitionRelation(?TablePartitionRelation $partitionRelation): self
    {
        return new self(
            $this->columns,
            $this->columnTypes,
            $this->primaryKeys,
            $this->notNullColumns,
            $this->uniqueConstraints,
            $this->typedColumns,
            $this->columnDefaults,
            $this->identityStrategies,
            $this->generatedExpressions,
            $this->foreignKeys,
            $this->partitioning,
            $this->partitionKey,
            $partitionRelation,
        );
    }
}

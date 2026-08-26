<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

final class MultiTableMutationTarget
{
    private string $tableName;
    /** @var list<string> */
    private array $columns;
    /** @var list<string> */
    private array $primaryKeys;

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     */
    public function __construct(string $tableName, array $columns, array $primaryKeys)
    {
        $this->tableName = $tableName;
        $this->columns = array_values($columns);
        $this->primaryKeys = array_values($primaryKeys);
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<string>
     */
    public function primaryKeys(): array
    {
        return $this->primaryKeys;
    }

    /**
     * @return list<string>
     */
    public function matchColumns(): array
    {
        return $this->primaryKeys !== [] ? $this->primaryKeys : $this->columns;
    }
}

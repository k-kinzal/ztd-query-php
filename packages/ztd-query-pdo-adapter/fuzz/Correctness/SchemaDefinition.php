<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

final class SchemaDefinition
{
    /**
     * @var array<int, string>
     */
    public readonly array $columns;

    /**
     * @var array<int, string>
     */
    public readonly array $primaryKeys;

    /**
     * @var array<int, string>
     */
    public readonly array $defaultColumns;

    /**
     * @var array<string, string>
     */
    public readonly array $columnTypes;

    /**
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     * @param array<int, string> $defaultColumns
     * @param array<string, string> $columnTypes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $sql,
        array $columns,
        array $primaryKeys,
        array $defaultColumns = [],
        array $columnTypes = [],
    ) {
        $this->columns = $columns;
        $this->primaryKeys = $primaryKeys;
        $this->defaultColumns = $defaultColumns;
        $this->columnTypes = $columnTypes;
    }
}

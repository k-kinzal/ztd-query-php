<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

/**
 * The schema definition.
 */
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
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     * @param array<int, string> $defaultColumns
     */
    public function __construct(
        public readonly string $name,
        public readonly string $sql,
        array $columns,
        array $primaryKeys,
        array $defaultColumns = [],
    ) {
        $this->columns = $columns;
        $this->primaryKeys = $primaryKeys;
        $this->defaultColumns = $defaultColumns;
    }
}

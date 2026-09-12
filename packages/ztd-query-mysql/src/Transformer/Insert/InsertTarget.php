<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Insert;

use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\IdentityGenerationStrategy;

/**
 * Validated target metadata and identity input for an INSERT projection.
 *
 * Accepts the public transformer context and narrows fixture cells to the only
 * values the identity allocator recognizes: integers and numeric strings.
 */
final class InsertTarget
{
    /**
     * tableName used to construct the projected row.
     */
    public readonly string $tableName;

    /**
     * tableColumns used to construct the projected row.
     * @var list<string>
     */
    public readonly array $tableColumns;

    /**
     * insertColumns used to construct the projected row.
     * @var list<string>
     */
    public readonly array $insertColumns;

    /**
     * columnTypes used to construct the projected row.
     * @var array<string, ColumnType>
     */
    public readonly array $columnTypes;

    /**
     * columnDefaults used to construct the projected row.
     * @var array<string, string>
     */
    public readonly array $columnDefaults;

    /**
     * identityStrategies used to construct the projected row.
     * @var array<string, IdentityGenerationStrategy>
     */
    public readonly array $identityStrategies;

    /**
     * existingRows used to construct the projected row.
     * @var array<int, array<string, int|string>>
     */
    public readonly array $existingRows;

    /**
     * candidateKeys used to construct the projected row.
     * @var array<string, array<int, string>>
     */
    public readonly array $candidateKeys;

    /**
     * Create a target from already validated column and identity metadata.
     * @param string $tableName
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $columnDefaults
     * @param array<string, IdentityGenerationStrategy> $identityStrategies
     * @param array<int, array<string, int|string>> $existingRows
     * @param array<string, array<int, string>> $candidateKeys
     */
    public function __construct(
        string $tableName,
        array $tableColumns,
        array $insertColumns,
        array $columnTypes,
        array $columnDefaults,
        array $identityStrategies,
        array $existingRows,
        array $candidateKeys,
    ) {
        $this->tableName = $tableName;
        $this->tableColumns = $tableColumns;
        $this->insertColumns = $insertColumns;
        $this->columnTypes = $columnTypes;
        $this->columnDefaults = $columnDefaults;
        $this->identityStrategies = $identityStrategies;
        $this->existingRows = $existingRows;
        $this->candidateKeys = $candidateKeys;
    }

    /**
     * @param array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>,
     *     columnDefaults?: array<string, string>,
     *     identityStrategies?: array<string, IdentityGenerationStrategy>,
     *     candidateKeys?: array<string, array<int, string>>
     * }> $tables
     * @throws UnsupportedSqlException
     */
    public static function fromStatement(InsertStatement $statement, array $tables, string $sql): self
    {
        if ($statement->into === null || $statement->into->dest === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }

        $dest = $statement->into->dest;
        $tableName = is_string($dest) ? $dest : ($dest->table ?? null);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $insertColumns = ResultProjection::orderedValues($statement->into->columns ?? []);
        $tableColumns = ResultProjection::orderedValues($tables[$tableName]['columns'] ?? $insertColumns);
        if ($tableColumns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $columnTypes = $tables[$tableName]['columnTypes'] ?? [];
        $columnDefaults = $tables[$tableName]['columnDefaults'] ?? [];
        $identityStrategies = $tables[$tableName]['identityStrategies'] ?? [];
        $existingRows = [];
        foreach ($tables[$tableName]['rows'] ?? [] as $row) {
            $identityRow = [];
            foreach ($row as $column => $value) {
                if (is_int($value) || is_string($value)) {
                    $identityRow[$column] = $value;
                }
            }
            $existingRows[] = $identityRow;
        }
        $candidateKeys = $tables[$tableName]['candidateKeys'] ?? [];
        return new self($tableName, $tableColumns, $insertColumns, $columnTypes, $columnDefaults, $identityStrategies, $existingRows, $candidateKeys);
    }
}

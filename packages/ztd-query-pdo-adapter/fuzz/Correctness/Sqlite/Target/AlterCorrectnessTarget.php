<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Sqlite\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use Fuzz\Correctness\Sqlite\SqliteSchemaPool;
use PDO;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class AlterCorrectnessTarget
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param SqliteCorrectnessHarness $harness
     * @param Generator $faker
     * @param ResultComparator $comparator
     */
    public function __construct(
        private readonly SqliteCorrectnessHarness $harness,
        private readonly Generator $faker,
        private readonly ResultComparator $comparator = new ResultComparator(),
    ) {
    }

    /**
     * @throws Error
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);
        $schema = SqliteSchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);
        $case = $this->buildCase($schema);

        try {
            $rawError = null;
            try {
                $this->harness->getRawPdo()->exec($case['sql']);
            } catch (PDOException $exception) {
                $rawError = $exception;
            }

            try {
                $this->harness->getZtdPdo()->exec($case['sql']);
            } catch (UnsupportedSqlException | UnknownSchemaException | DatabaseException | PDOException $exception) {
                if ($rawError !== null) {
                    return;
                }
                throw new Error("ZTD ALTER failed after native success\nSeed: $seed\nSQL: {$case['sql']}", 0, $exception);
            }
            if ($rawError !== null) {
                return;
            }

            $rawRows = $this->fetchAll($this->harness->getRawPdo(), $case['resultTable']);
            $ztdRows = $this->fetchAll($this->harness->getZtdPdo(), $case['resultTable']);
            foreach ($ztdRows as $row) {
                if (array_keys($row) !== $case['columns']) {
                    throw new Error(
                        "ALTER table schema mismatch\n"
                        . "Seed: $seed\n"
                        . "SQL: {$case['sql']}\n"
                        . 'Expected columns: ' . json_encode($case['columns'], JSON_THROW_ON_ERROR) . "\n"
                        . 'ZTD columns: ' . json_encode(array_keys($row), JSON_THROW_ON_ERROR),
                    );
                }
            }
            if (!$this->comparator->compareRows($rawRows, $ztdRows, $case['primaryKeys'])) {
                throw new Error(
                    "ALTER table state mismatch\n"
                    . "Seed: $seed\n"
                    . "SQL: {$case['sql']}\n"
                    . 'Raw rows: ' . json_encode($rawRows, JSON_THROW_ON_ERROR) . "\n"
                    . 'ZTD rows: ' . json_encode($ztdRows, JSON_THROW_ON_ERROR),
                );
            }
            if ($case['removedTable'] !== null) {
                $removedRows = $this->fetchAll($this->harness->getZtdPdo(), $case['removedTable']);
                if ($removedRows !== []) {
                    throw new Error("Renamed source exposed physical rows\nSeed: $seed\nSQL: {$case['sql']}");
                }
            }
        } finally {
            if ($case['resultTable'] !== $schema->name) {
                $this->harness->getRawPdo()->exec('DROP TABLE IF EXISTS ' . $this->quote($case['resultTable']));
            }
            $this->harness->teardown();
        }
    }

    /**
     * @return array{
     *     sql: string,
     *     resultTable: string,
     *     columns: list<string>,
     *     primaryKeys: list<string>,
     *     removedTable: string|null
     * }
     */
    private function buildCase(SchemaDefinition $schema): array
    {
        $columns = array_values($schema->columns);
        $primaryKeys = array_values($schema->primaryKeys);
        $table = $this->quote($schema->name);
        $variant = $this->faker->numberBetween(0, 3);

        if ($variant === 0) {
            $default = $this->faker->numberBetween(-100, 100);
            $columns[] = 'ztd_added';

            return [
                'sql' => "ALTER TABLE $table ADD COLUMN \"ztd_added\" INTEGER NOT NULL DEFAULT $default",
                'resultTable' => $schema->name,
                'columns' => $columns,
                'primaryKeys' => $primaryKeys,
                'removedTable' => null,
            ];
        }

        if ($variant === 1) {
            $target = $schema->name . '_renamed';

            return [
                'sql' => 'ALTER TABLE ' . $table . ' RENAME TO ' . $this->quote($target),
                'resultTable' => $target,
                'columns' => $columns,
                'primaryKeys' => $primaryKeys,
                'removedTable' => $schema->name,
            ];
        }

        if ($variant === 2) {
            $oldName = $this->randomColumn($columns);
            $newName = 'ztd_renamed';

            return [
                'sql' => 'ALTER TABLE ' . $table . ' RENAME COLUMN ' . $this->quote($oldName) . ' TO ' . $this->quote($newName),
                'resultTable' => $schema->name,
                'columns' => $this->renamedColumns($columns, $oldName, $newName),
                'primaryKeys' => $this->renamedColumns($primaryKeys, $oldName, $newName),
                'removedTable' => null,
            ];
        }

        $droppable = array_values(array_diff($columns, $primaryKeys));
        $dropped = $this->randomColumn($droppable);

        return [
            'sql' => 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $this->quote($dropped),
            'resultTable' => $schema->name,
            'columns' => array_values(array_diff($columns, [$dropped])),
            'primaryKeys' => $primaryKeys,
            'removedTable' => null,
        ];
    }

    /**
     * @param list<string> $columns
     *
     * @throws Error
     */
    private function randomColumn(array $columns): string
    {
        $column = $this->faker->randomElement($columns);
        if (!is_string($column)) {
            throw new Error('ALTER correctness case has no candidate column');
        }

        return $column;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function renamedColumns(array $columns, string $old, string $new): array
    {
        $renamed = [];
        foreach ($columns as $column) {
            $renamed[] = $column === $old ? $new : $column;
        }

        return $renamed;
    }

    /**
     * @return list<Row>
     *
     * @throws Error
     */
    private function fetchAll(PDO $pdo, string $table): array
    {
        $statement = $pdo->query('SELECT * FROM ' . $this->quote($table));
        if ($statement === false) {
            return [];
        }

        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new Error('ALTER correctness query returned a non-array row');
            }
            $normalized = [];
            foreach ($row as $column => $value) {
                if (!is_string($column)) {
                    throw new Error('ALTER correctness query returned a non-string column');
                }
                $normalized[$column] = $value;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

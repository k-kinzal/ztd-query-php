<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Postgres\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Postgres\PgSchemaPool;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use PDO;
use PDOException;
use Throwable;

final class CreateTableAsCorrectnessTarget
{
    public function __construct(
        private readonly PgCorrectnessHarness $harness,
        private readonly Generator $faker,
        private readonly ResultComparator $comparator = new ResultComparator(),
    ) {
    }

    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);
        $schema = PgSchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);
        $copy = '_ztd_ctas_copy';
        $case = $this->buildCase($schema, $copy);

        try {
            $this->harness->getRawPdo()->exec('DROP TABLE IF EXISTS ' . $this->quote($copy));
            $this->harness->getRawPdo()->exec($case['sql']);

            try {
                $this->harness->getZtdPdo()->exec($case['sql']);
            } catch (Throwable $exception) {
                throw new Error("ZTD CTAS failed after native success\nSeed: $seed\nSQL: {$case['sql']}", 0, $exception);
            }

            $this->compareQuery('SELECT * FROM ' . $this->quote($copy), $case['sql'], $seed, $schema);
            $this->compareQuery(
                'SELECT * FROM ' . $this->quote($copy) . ' WHERE ' . $case['predicate'],
                $case['sql'],
                $seed,
                $schema,
            );
        } catch (PDOException) {
            return;
        } finally {
            $this->harness->getRawPdo()->exec('DROP TABLE IF EXISTS ' . $this->quote($copy));
            $this->harness->teardown();
        }
    }

    /**
     * @return array{sql: string, predicate: string}
     */
    private function buildCase(SchemaDefinition $schema, string $copy): array
    {
        $table = $this->quote($schema->name);
        $target = $this->quote($copy);
        $key = $schema->primaryKeys[0] ?? $schema->columns[0];
        $quotedKey = $this->quote($key);

        return match ($this->faker->numberBetween(0, 4)) {
            0 => [
                'sql' => "CREATE TABLE $target AS SELECT * FROM $table WHERE FALSE",
                'predicate' => "$quotedKey = 1",
            ],
            1 => [
                'sql' => "CREATE TABLE $target AS SELECT * FROM $table",
                'predicate' => "$quotedKey = 1",
            ],
            2 => [
                'sql' => "CREATE TABLE $target AS SELECT $quotedKey FROM $table WHERE $quotedKey >= 1",
                'predicate' => "$quotedKey = 1",
            ],
            3 => [
                'sql' => "CREATE TABLE $target AS SELECT $quotedKey + 1 AS ztd_value FROM $table",
                'predicate' => 'ztd_value = 2',
            ],
            default => [
                'sql' => "CREATE TABLE $target AS SELECT $quotedKey AS ztd_alias FROM $table WHERE FALSE",
                'predicate' => 'ztd_alias = 1',
            ],
        };
    }

    private function compareQuery(string $query, string $createSql, int $seed, SchemaDefinition $schema): void
    {
        $rawRows = $this->normalizeFixedCharacterRows(
            $this->fetchAll($this->harness->getRawPdo(), $query),
            $schema,
        );
        try {
            $ztdRows = $this->normalizeFixedCharacterRows(
                $this->fetchAll($this->harness->getZtdPdo(), $query),
                $schema,
            );
        } catch (Throwable $exception) {
            throw new Error(
                "ZTD CTAS query failed after native success\nSeed: $seed\nSQL: $createSql\nQuery: $query",
                0,
                $exception,
            );
        }

        if (!$this->comparator->compareRows($rawRows, $ztdRows, [], [], true)) {
            throw new Error(
                "CTAS result mismatch\n"
                . "Seed: $seed\n"
                . "SQL: $createSql\n"
                . "Query: $query\n"
                . 'Native: ' . json_encode($rawRows, JSON_THROW_ON_ERROR) . "\n"
                . 'ZTD: ' . json_encode($ztdRows, JSON_THROW_ON_ERROR),
            );
        }
    }

    /**
     * PostgreSQL pads CHAR values when reading a physical table, while the existing
     * shadow source stores their semantically equivalent unpadded representation.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFixedCharacterRows(array $rows, SchemaDefinition $schema): array
    {
        if ($schema->name !== 'text_types') {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            $value = $row['col_char'] ?? null;
            if (is_string($value)) {
                $rows[$index]['col_char'] = rtrim($value, ' ');
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAll(PDO $pdo, string $query): array
    {
        $statement = $pdo->query($query);
        if ($statement === false) {
            return [];
        }

        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new Error('CTAS correctness query returned a non-array row');
            }
            $normalized = [];
            foreach ($row as $column => $value) {
                if (!is_string($column)) {
                    throw new Error('CTAS correctness query returned a non-string column');
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

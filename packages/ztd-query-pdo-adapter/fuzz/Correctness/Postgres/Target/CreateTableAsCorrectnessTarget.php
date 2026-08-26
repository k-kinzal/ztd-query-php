<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Postgres\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Postgres\PgSchemaPool;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use JsonException;
use PDO;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * @phpstan-import-type Row from StatementInterface
 */
final class CreateTableAsCorrectnessTarget
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param PgCorrectnessHarness $harness
     * @param Generator $faker
     * @param ResultComparator $comparator
     */
    public function __construct(
        private readonly PgCorrectnessHarness $harness,
        private readonly Generator $faker,
        private readonly ResultComparator $comparator = new ResultComparator(),
    ) {
    }

    /**
     * @throws Error
     * @throws JsonException
     */
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
            } catch (UnsupportedSqlException | UnknownSchemaException | DatabaseException | PDOException $exception) {
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
     * Answers one case to run, as the statement and what it runs against.
     *
     * @param SchemaDefinition $schema The schema
     * @param string $copy The copy
     *
     * @return array{sql: string, predicate: string} What it answers
     */
    public function buildCase(SchemaDefinition $schema, string $copy): array
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

    /**
     * Runs the query on both sides and fails if they disagree.
     *
     * @param string $query The query
     * @param string $createSql The create sql
     * @param int $seed The seed
     * @param SchemaDefinition $schema The schema
     *
     * @throws Error
     * @throws JsonException
     */
    public function compareQuery(string $query, string $createSql, int $seed, SchemaDefinition $schema): void
    {
        $rawRows = $this->fetchAll($this->harness->getRawPdo(), $query);
        try {
            $ztdRows = $this->fetchAll($this->harness->getZtdPdo(), $query);
        } catch (UnsupportedSqlException | UnknownSchemaException | DatabaseException | PDOException $exception) {
            throw new Error(
                "ZTD CTAS query failed after native success\nSeed: $seed\nSQL: $createSql\nQuery: $query",
                0,
                $exception,
            );
        }

        if (!$this->comparator->compareRows($rawRows, $ztdRows, [], $schema->columnTypes, true)) {
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
     * Answers every row the connection reads.
     *
     * @param PDO $pdo The pdo
     * @param string $query The query
     *
     * @return list<Row> What it answers
     *
     * @throws Error
     */
    public function fetchAll(PDO $pdo, string $query): array
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
                if ($value !== null && !is_scalar($value)) {
                    throw new Error('CTAS correctness query returned a value no comparison can read');
                }
                $normalized[$column] = $value;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * Answers the identifier as the dialect quotes it.
     *
     * @param string $identifier Name, as it was written
     *
     * @return string What it answers
     */
    public function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

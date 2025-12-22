<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Sqlite\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use Fuzz\Correctness\Sqlite\SqliteSchemaAwareSqlBuilder;
use Fuzz\Correctness\Sqlite\SqliteSchemaPool;
use PDO;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

final class DeleteCorrectnessTarget
{
    private SqliteCorrectnessHarness $harness;
    private ResultComparator $comparator;
    private SqliteSchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    public function __construct(
        SqliteCorrectnessHarness $harness,
        SqliteSchemaAwareSqlBuilder $sqlBuilder,
        Generator $faker
    ) {
        $this->harness = $harness;
        $this->comparator = new ResultComparator();
        $this->sqlBuilder = $sqlBuilder;
        $this->faker = $faker;
    }

    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $schema = SqliteSchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);

        try {
            $sql = $this->sqlBuilder->buildDelete($schema);

            $rawError = null;
            try {
                $this->harness->getRawPdo()->exec($sql);
            } catch (PDOException $e) {
                $rawError = $e;
            }

            try {
                $this->harness->getZtdPdo()->exec($sql);
            } catch (UnsupportedSqlException | UnknownSchemaException) {
                return;
            } catch (DatabaseException | PDOException) {
                return;
            }

            if ($rawError !== null) {
                return;
            }

            $this->compareTableState($schema, $seed);
        } finally {
            $this->harness->teardown();
        }
    }

    private function compareTableState(SchemaDefinition $schema, int $seed): void
    {
        $rawRows = $this->fetchAll($this->harness->getRawPdo(), $schema->name);

        $selectSql = sprintf('SELECT * FROM "%s"', str_replace('"', '""', $schema->name));
        $stmt = $this->harness->getZtdPdo()->query($selectSql);
        /** @var array<int, array<string, mixed>> $ztdRows */
        $ztdRows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
            throw new Error(
                "DELETE table state mismatch\n" .
                "Seed: $seed\n" .
                "Schema: {$schema->name}\n" .
                "Raw row count: " . count($rawRows) . "\n" .
                "ZTD row count: " . count($ztdRows)
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query(sprintf('SELECT * FROM "%s"', str_replace('"', '""', $table)));
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $rows;
    }
}

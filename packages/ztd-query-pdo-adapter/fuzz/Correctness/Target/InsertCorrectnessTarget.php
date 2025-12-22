<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Fuzz\Correctness\CorrectnessHarness;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\SchemaPool;
use Faker\Generator;
use PDO;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

final class InsertCorrectnessTarget
{
    private CorrectnessHarness $harness;
    private ResultComparator $comparator;
    private SchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    public function __construct(
        CorrectnessHarness $harness,
        SchemaAwareSqlBuilder $sqlBuilder,
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

        $schema = SchemaPool::random($this->faker);
        $this->harness->setup($schema, $seed);

        try {
            $sql = $this->sqlBuilder->buildInsert($schema);

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

    private function compareTableState(\Fuzz\Correctness\SchemaDefinition $schema, int $seed): void
    {
        $rawRows = $this->fetchAll($this->harness->getRawPdo(), $schema->name);

        $selectSql = "SELECT * FROM `{$schema->name}`";
        $stmt = $this->harness->getZtdPdo()->query($selectSql);
        /** @var array<int, array<string, mixed>> $ztdRows */
        $ztdRows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
            throw new Error(
                "INSERT table state mismatch\n" .
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
        $stmt = $pdo->query("SELECT * FROM `$table`");
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $rows;
    }
}

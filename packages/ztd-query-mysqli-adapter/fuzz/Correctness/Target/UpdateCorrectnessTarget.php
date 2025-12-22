<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\SchemaDefinition;
use Fuzz\Correctness\SchemaPool;
use Faker\Generator;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

final class UpdateCorrectnessTarget
{
    private MysqliCorrectnessHarness $harness;
    private ResultComparator $comparator;
    private SchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    public function __construct(
        MysqliCorrectnessHarness $harness,
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
            $sql = $this->sqlBuilder->buildUpdate($schema);

            $rawError = null;
            try {
                $this->harness->getRawMysqli()->query($sql);
            } catch (mysqli_sql_exception $e) {
                $rawError = $e;
            }

            try {
                $this->harness->getZtdMysqli()->query($sql);
            } catch (UnsupportedSqlException | UnknownSchemaException) {
                return;
            } catch (DatabaseException | mysqli_sql_exception) {
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
        $rawRows = $this->fetchAll($this->harness->getRawMysqli(), $schema->name);

        $result = $this->harness->getZtdMysqli()->query("SELECT * FROM `{$schema->name}`");
        /** @var array<int, array<string, mixed>> $ztdRows */
        $ztdRows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if (!$this->comparator->compareRows($rawRows, $ztdRows, $schema->primaryKeys)) {
            throw new Error(
                "UPDATE table state mismatch\n" .
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
    private function fetchAll(mysqli $mysqli, string $table): array
    {
        $result = $mysqli->query("SELECT * FROM `$table`");
        if (!$result instanceof mysqli_result) {
            return [];
        }
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
}

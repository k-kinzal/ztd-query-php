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
use mysqli_result;
use mysqli_sql_exception;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

final class SelectCorrectnessTarget
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
            $queryCount = $this->faker->numberBetween(1, 5);
            for ($i = 0; $i < $queryCount; $i++) {
                $sql = $this->sqlBuilder->buildSelect($schema);
                $this->compareSelect($sql, $schema, $seed);
            }
        } finally {
            $this->harness->teardown();
        }
    }

    private function compareSelect(string $sql, SchemaDefinition $schema, int $seed): void
    {
        /** @var array<int, array<string, mixed>>|null $rawResult */
        $rawResult = null;
        $rawError = null;
        try {
            $result = $this->harness->getRawMysqli()->query($sql);
            if ($result instanceof mysqli_result) {
                /** @var array<int, array<string, mixed>> $rawResult */
                $rawResult = $result->fetch_all(MYSQLI_ASSOC);
            }
        } catch (mysqli_sql_exception $e) {
            $rawError = $e;
        }

        /** @var array<int, array<string, mixed>>|null $ztdResult */
        $ztdResult = null;
        $ztdError = null;
        try {
            $result = $this->harness->getZtdMysqli()->query($sql);
            if ($result instanceof mysqli_result) {
                /** @var array<int, array<string, mixed>> $ztdResult */
                $ztdResult = $result->fetch_all(MYSQLI_ASSOC);
            }
        } catch (UnsupportedSqlException | UnknownSchemaException) {
            return;
        } catch (DatabaseException) {
            return;
        } catch (mysqli_sql_exception $e) {
            $ztdError = $e;
        }

        if ($rawError !== null && $ztdError !== null) {
            return;
        }

        if ($rawError !== null || $ztdError !== null) {
            return;
        }

        if ($rawResult !== null && $ztdResult !== null) {
            $hasOrderBy = stripos($sql, 'ORDER BY') !== false;
            if (!$this->comparator->compareRows($rawResult, $ztdResult, $schema->primaryKeys, [], !$hasOrderBy)) {
                throw new Error(
                    "SELECT result mismatch\n" .
                    "Seed: $seed\n" .
                    "SQL: $sql\n" .
                    "Schema: {$schema->name}\n" .
                    "Raw result count: " . count($rawResult) . "\n" .
                    "ZTD result count: " . count($ztdResult) . "\n" .
                    "Raw first row: " . json_encode($rawResult[0] ?? null) . "\n" .
                    "ZTD first row: " . json_encode($ztdResult[0] ?? null)
                );
            }
        }
    }
}

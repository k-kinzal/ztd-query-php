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

final class SelectCorrectnessTarget
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
            $queryCount = $this->faker->numberBetween(1, 5);
            for ($i = 0; $i < $queryCount; $i++) {
                $sql = $this->sqlBuilder->buildSelect($schema);
                $this->compareSelect($sql, $schema, $seed);
            }
        } finally {
            $this->harness->teardown();
        }
    }

    private function compareSelect(string $sql, \Fuzz\Correctness\SchemaDefinition $schema, int $seed): void
    {
        /** @var array<int, array<string, mixed>>|null $rawResult */
        $rawResult = null;
        $rawError = null;
        try {
            $stmt = $this->harness->getRawPdo()->query($sql);
            /** @var array<int, array<string, mixed>>|null $rawResult */
            $rawResult = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : null;
        } catch (PDOException $e) {
            $rawError = $e;
        }

        /** @var array<int, array<string, mixed>>|null $ztdResult */
        $ztdResult = null;
        $ztdError = null;
        try {
            $stmt = $this->harness->getZtdPdo()->query($sql);
            /** @var array<int, array<string, mixed>>|null $ztdResult */
            $ztdResult = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : null;
        } catch (UnsupportedSqlException | UnknownSchemaException) {
            return;
        } catch (DatabaseException) {
            if ($rawError !== null) {
                return;
            }
            return;
        } catch (PDOException $e) {
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

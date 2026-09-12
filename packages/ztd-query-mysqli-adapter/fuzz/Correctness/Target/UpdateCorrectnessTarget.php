<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\SchemaPool;
use Fuzz\Correctness\TableStateOracle;
use mysqli_sql_exception;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

/**
 * Compares native and simulated UPDATE operations from reproducible inputs.
 */
final class UpdateCorrectnessTarget
{
    private MysqliCorrectnessHarness $harness;
    private SchemaAwareSqlBuilder $sqlBuilder;
    private Generator $faker;

    /**
     * Bind the connection, schema generator and deterministic input dependencies.
     */
    public function __construct(
        MysqliCorrectnessHarness $harness,
        SchemaAwareSqlBuilder $sqlBuilder,
        Generator $faker
    ) {
        $this->harness = $harness;
        $this->sqlBuilder = $sqlBuilder;
        $this->faker = $faker;
    }

    /**
     * Execute one seeded scenario and reset all mutable database state.
     *
     * @throws Error When native and simulated behavior differ.
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $schema = SchemaPool::random($this->faker);
        try {
            $this->harness->setup($schema, $seed);
            $sql = $this->sqlBuilder->buildUpdate($schema);

            $rawError = null;
            try {
                $this->harness->getRawMysqli()->query($sql);
            } catch (mysqli_sql_exception $e) {
                $rawError = $e;
            }

            try {
                $this->harness->getZtdMysqli()->query($sql);
            } catch (ZtdMysqliException | mysqli_sql_exception $exception) {
                if ($rawError === null) {
                    throw new Error("ZTD mutation failed after native success\nSeed: $seed\nSQL: $sql", 0, $exception);
                }
                return;
            }

            if ($rawError !== null) {
                return;
            }

            (new TableStateOracle($this->harness))->compare($schema, $seed, $sql);
        } finally {
            $this->harness->teardown();
        }
    }




}

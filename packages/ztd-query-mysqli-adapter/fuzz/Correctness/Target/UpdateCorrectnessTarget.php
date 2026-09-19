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

            if ($schema->primaryKeys === []) {
                $this->verifyMissingPrimaryKey($sql, $schema, $seed);
                return;
            }

            $rawError = null;
            try {
                /** @throws mysqli_sql_exception */
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
                \Fuzz\Correctness\FailureComparison::verify($rawError, $exception, $sql);
                (new TableStateOracle($this->harness))->compare($schema, $seed, $sql);
                return;
            }

            if ($rawError !== null) {
                throw new Error("ZTD mutation accepted a native-rejected query\nSeed: $seed\nSQL: $sql", 0, $rawError);
            }

            (new TableStateOracle($this->harness))->compare($schema, $seed, $sql);
        } finally {
            $this->harness->teardown();
        }
    }




    /**
     * Verify the declared no-primary-key rejection without executing the native write.
     *
     * @throws Error When the rejection differs or changes shadow state.
     */
    public function verifyMissingPrimaryKey(string $sql, \Fuzz\Correctness\SchemaDefinition $schema, int $seed): void
    {
        try {
            $this->harness->getZtdMysqli()->query($sql);
        } catch (ZtdMysqliException $failure) {
            for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
                if ($cause instanceof \ZtdQuery\Exception\MissingPrimaryKeyException) {
                    (new TableStateOracle($this->harness))->compare($schema, $seed, $sql);
                    return;
                }
            }
            throw new Error('Unexpected rejection for an UPDATE without a primary key: ' . $sql, 0, $failure);
        }
        throw new Error('UPDATE without a primary key must reject without changing shadow rows: ' . $sql);
    }

}

<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\SchemaPool;
use Fuzz\Correctness\TableStateOracle;
use mysqli_sql_exception;
use RuntimeException;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

/**
 * Compares native and simulated REPLACE operations from reproducible inputs.
 */
final class ReplaceCorrectnessTarget
{
    /**
     * Bind the connection, schema generator and deterministic input dependencies.
     */
    public function __construct(
        private readonly MysqliCorrectnessHarness $harness,
        private readonly Generator $faker,
    ) {
    }

    /**
     * Execute one seeded scenario and reset all mutable database state.
     *
     * @throws RuntimeException If the native statement cannot be prepared.
     * @throws Error When native and simulated behavior differ.
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);
        $schema = SchemaPool::random($this->faker);
        if ($schema->primaryKeys === []) {
            return;
        }

        $fixtures = $this->harness->setup($schema, $seed);
        $row = $fixtures[$seed % count($fixtures)];
        $columns = array_keys($row);
        $sql = sprintf(
            'REPLACE INTO `%s` (%s) VALUES (%s)',
            $schema->name,
            implode(', ', array_map(static fn (string $column): string => "`$column`", $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
        );
        $params = array_values($row);

        try {
            $statement = $this->harness->getRawMysqli()->prepare($sql);
            if ($statement === false) {
                throw new RuntimeException('Native REPLACE could not be prepared.');
            }
            $statement->execute($params);
            try {
                $this->harness->getZtdMysqli()->execute_query($sql, $params);
            } catch (ZtdMysqliException | mysqli_sql_exception $exception) {
                throw new Error("ZTD prepared REPLACE failed after native success\nSeed: $seed\nSQL: $sql", 0, $exception);
            }

            (new TableStateOracle($this->harness))->compare($schema, $seed, $sql);
        } finally {
            $this->harness->teardown();
        }
    }




}

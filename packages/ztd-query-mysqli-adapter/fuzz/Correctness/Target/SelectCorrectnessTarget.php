<?php

declare(strict_types=1);

namespace Fuzz\Correctness\Target;

use Error;
use Faker\Generator;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\SchemaPool;
use Fuzz\Correctness\SelectResultOracle;

/**
 * Compares native and simulated SELECT operations from reproducible inputs.
 */
final class SelectCorrectnessTarget
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
            $queryCount = $this->faker->numberBetween(1, 5);
            for ($i = 0; $i < $queryCount; $i++) {
                $sql = $this->sqlBuilder->buildSelect($schema);
                (new SelectResultOracle($this->harness))->compareSelect($sql, $schema, $seed);
            }
        } finally {
            $this->harness->teardown();
        }
    }


}

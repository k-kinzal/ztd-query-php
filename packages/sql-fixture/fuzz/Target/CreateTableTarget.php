<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use SqlFaker\MySqlProvider;
use SqlFixture\FixtureProvider;

/**
 * Fuzz target for CREATE TABLE parsing and fixture generation.
 *
 * This target uses sql-faker to generate CREATE TABLE statements,
 * then validates that sql-fixture can parse them and generate fixtures.
 */
final class CreateTableTarget
{
    private Generator $faker;
    private MySqlProvider $sqlFakerProvider;
    private FixtureProvider $fixtureProvider;

    public function __construct(
        string $grammarVersion,
        private readonly int $maxDepth = 5,
    ) {
        $this->faker = Factory::create();
        $this->sqlFakerProvider = new MySqlProvider($this->faker, $grammarVersion);
        $this->fixtureProvider = new FixtureProvider($this->faker);
    }

    /**
     * Fuzz target callable.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     * @throws Error On parsing or fixture generation failure
     */
    public function __invoke(string $input): void
    {
        $seed = $this->inputToSeed($input);
        $this->faker->seed($seed);

        $createTableSql = $this->sqlFakerProvider->createTableStatement(maxDepth: $this->maxDepth);

        try {
            $this->fixtureProvider->fixture($createTableSql);
        } catch (\Throwable $e) {
            if ($e instanceof Error) {
                throw $e;
            }

            if ($this->isExpectedParserLimitation($createTableSql, $e)) {
                return;
            }

            throw new Error(
                "Failed to generate fixture\n" .
                "Seed: $seed\n" .
                "SQL: $createTableSql\n" .
                "Error: {$e->getMessage()}\n" .
                "Exception: " . get_class($e)
            );
        }
    }

    private function inputToSeed(string $input): int
    {
        if (strlen($input) < 4) {
            $input = str_pad($input, 4, "\0");
        }
        return crc32($input);
    }

    /**
     * Check if the failure is an expected limitation of the parser.
     */
    private function isExpectedParserLimitation(string $sql, \Throwable $e): bool
    {
        $message = $e->getMessage();

        if (str_contains($message, 'No columns found')) {
            return true;
        }
        if (str_contains($message, 'Table name not found')) {
            return true;
        }
        if (str_contains($message, 'No statements found')) {
            return true;
        }

        return false;
    }
}

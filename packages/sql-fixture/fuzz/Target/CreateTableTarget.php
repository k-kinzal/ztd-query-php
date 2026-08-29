<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use Faker\Factory;
use Faker\Generator;
use Fuzz\FuzzerSeed;
use SqlFaker\MySqlStatementProvider;
use SqlFixture\FixtureProvider;
use SqlFixture\Schema\SchemaParseException;

/**
 * Fuzz target for CREATE TABLE parsing and fixture generation.
 *
 * This target uses sql-faker to generate CREATE TABLE statements,
 * then validates that sql-fixture can parse them and generate fixtures.
 */
final class CreateTableTarget
{
    private Generator $faker;
    private MySqlStatementProvider $sqlFakerProvider;
    private FixtureProvider $fixtureProvider;

    /**
     * Builds a target that generates a table and then a fixture for it.
     *
     * @param string $grammarVersion Release whose grammar the SQL is generated from
     * @param int $maxDepth How deep the grammar walk may recurse
     * @param FuzzerSeed $seeds Turns fuzzer bytes into a seed
     * @param ParserLimitations $limitations Recognises the reader's known limits
     */
    public function __construct(
        string $grammarVersion,
        private readonly int $maxDepth = 5,
        private readonly FuzzerSeed $seeds = new FuzzerSeed(),
        private readonly ParserLimitations $limitations = new ParserLimitations(),
    ) {
        $this->faker = Factory::create();
        $this->sqlFakerProvider = new MySqlStatementProvider($this->faker, $grammarVersion);
        $this->fixtureProvider = new FixtureProvider($this->faker);
    }

    /**
     * Generates one table declaration and builds a fixture for it.
     *
     * @param string $input Raw fuzzer input
     *
     * @throws Error When the fixture cannot be built for a reason that is not a known limit
     */
    public function __invoke(string $input): void
    {
        $seed = $this->seeds->of($input);
        $this->faker->seed($seed);

        $createTableSql = $this->sqlFakerProvider->createTableStatement(maxDepth: $this->maxDepth);

        try {
            $this->fixtureProvider->fixture($createTableSql);
        } catch (SchemaParseException $failure) {
            if ($this->limitations->explains($failure)) {
                return;
            }

            throw new Error(sprintf(
                "Failed to generate fixture\nSeed: %d\nSQL: %s\nError: %s\nException: %s",
                $seed,
                $createTableSql,
                $failure->getMessage(),
                $failure::class,
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use SqlFaker\SqliteProvider;

/**
 * Fuzz target for SQLite SQL syntax validation.
 *
 * Each call derives an RNG seed from the fuzzer input, generates one statement
 * from the SQLite grammar with that seed, and checks that SQLite parses it.
 * Deriving the seed from the input is what lets php-fuzzer shrink a finding and
 * replay it from the corpus entry alone.
 */
final class SqliteSyntaxTarget
{
    private readonly Generator $faker;

    private readonly SqliteProvider $provider;

    private readonly CorpusSeed $seed;

    private readonly SqliteSyntaxCheck $check;

    /**
     * @param PDO $pdo Connection to the in-memory SQLite database under test
     * @param int $maxDepth Maximum grammar expansion depth
     */
    public function __construct(
        PDO $pdo,
        private readonly int $maxDepth = 8,
    ) {
        $this->faker = Factory::create();
        $this->provider = new SqliteProvider($this->faker);
        $this->seed = new CorpusSeed();
        $this->check = new SqliteSyntaxCheck($pdo);
    }

    /**
     * Generates one statement from the fuzzer input and validates it.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     */
    public function __invoke(string $input): void
    {
        $seed = $this->seed->forInput($input);
        $this->faker->seed($seed);

        $this->check->verify($this->provider->sql(maxDepth: $this->maxDepth), $seed);
    }
}

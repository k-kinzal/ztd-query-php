<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use SqlFaker\MySqlProvider;

/**
 * Fuzz target for MySQL SQL syntax validation.
 *
 * Each call derives an RNG seed from the fuzzer input, generates one statement
 * from the MySQL grammar with that seed, and checks that MySQL parses it.
 * Deriving the seed from the input is what lets php-fuzzer shrink a finding and
 * replay it from the corpus entry alone.
 */
final class MySqlSyntaxTarget
{
    private readonly Generator $faker;

    private readonly MySqlProvider $provider;

    private readonly CorpusSeed $seed;

    private readonly MySqlSyntaxCheck $check;

    /**
     * @param PDO $pdo Connection to the MySQL instance under test
     * @param string $grammarVersion Grammar version to generate against, e.g. "mysql-8.0.44"
     * @param int $maxDepth Maximum grammar expansion depth
     */
    public function __construct(
        PDO $pdo,
        string $grammarVersion,
        private readonly int $maxDepth = 8,
    ) {
        $this->faker = Factory::create();
        $this->provider = new MySqlProvider($this->faker, $grammarVersion);
        $this->seed = new CorpusSeed();
        $this->check = new MySqlSyntaxCheck($pdo, $grammarVersion);
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

        $this->check->verify($this->provider->sqlWithoutEmptyRows(maxDepth: $this->maxDepth), $seed);
    }
}

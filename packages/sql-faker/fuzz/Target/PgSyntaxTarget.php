<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use PDO;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\PostgreSqlStatementProvider;

/**
 * Fuzz target for PostgreSQL SQL syntax validation.
 *
 * Each call derives an RNG seed from the fuzzer input, generates one statement
 * from the PostgreSQL grammar with that seed, and checks that PostgreSQL
 * accepts it. Deriving the seed from the input is what lets php-fuzzer shrink a
 * finding and replay it from the corpus entry alone.
 *
 * PostgreSQL prepares statements lazily, so PDO::prepare() cannot be used to
 * validate syntax. Statements are executed inside savepoints instead, which
 * requires a transaction to be open for the lifetime of the target.
 */
final class PgSyntaxTarget
{
    private readonly Generator $faker;

    private readonly PostgreSqlProvider $provider;

    /** @readonly */
    private PostgreSqlStatementProvider $statements;

    private readonly CorpusSeed $seed;

    private readonly PgSyntaxCheck $check;

    /**
     * @param PDO $pdo Connection to the PostgreSQL instance under test
     * @param int $maxDepth Maximum grammar expansion depth
     */
    public function __construct(
        PDO $pdo,
        private readonly int $maxDepth = 8,
    ) {
        $this->faker = Factory::create();
        $this->provider = new PostgreSqlProvider($this->faker);
        $this->statements = new PostgreSqlStatementProvider($this->faker);
        $this->seed = new CorpusSeed();
        $this->check = new PgSyntaxCheck($pdo, new PgBracketIndirection());

        $pdo->exec('BEGIN');
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

        $sql = $seed % 4 === 0
            ? $this->statements->createTableAsStatement(maxDepth: $this->maxDepth)
            : $this->provider->sql(maxDepth: $this->maxDepth);

        $this->check->verify($sql, $seed);
    }
}

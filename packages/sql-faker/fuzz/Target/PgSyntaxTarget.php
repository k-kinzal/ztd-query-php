<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Faker\Generator;
use Fuzz\Policy\PostgreSqlFuzzPolicy;
use PDO;
use Fuzz\Probe\PostgreSqlEngineProbe;
use SqlFaker\PostgreSqlProvider;

/**
 * Fuzz target for PostgreSQL SQL syntax validation.
 *
 * This target converts fuzzer input to RNG seed, generates SQL via grammar,
 * and validates it against PostgreSQL. Errors (not Exceptions) indicate bugs.
 */
final class PgSyntaxTarget
{
    private Generator $faker;

    private PostgreSqlProvider $provider;

    private PostgreSqlEngineProbe $probe;

    private PostgreSqlFuzzPolicy $policy;

    private int $maxDepth;

    public function __construct(
        PDO $pdo,
        int $maxDepth = 8
    ) {
        $this->maxDepth = $maxDepth;

        $this->faker = Factory::create();
        $this->provider = new PostgreSqlProvider($this->faker);
        $this->probe = new PostgreSqlEngineProbe($pdo);
        $this->policy = new PostgreSqlFuzzPolicy();
    }

    /**
     * Fuzz target callable.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     *
     * @throws \Error On syntax validation failure (caught by fuzzer)
     */
    public function __invoke(string $input): void
    {
        $seed = $this->inputToSeed($input);
        $this->faker->seed($seed);

        $sql = $this->provider->sql(maxDepth: $this->maxDepth);

        $this->validateSyntax($sql, $seed);
    }

    private function inputToSeed(string $input): int
    {
        if (strlen($input) < 4) {
            $input = str_pad($input, 4, "\0");
        }

        return crc32($input);
    }

    private function validateSyntax(string $sql, int $seed): void
    {
        if ($sql === '') {
            return;
        }

        $probeResult = $this->probe->observe($sql);
        $decision = $this->policy->classify($probeResult);
        if (!$decision->shouldCrash) {
            return;
        }

        throw new \Error(
            "Unexpected error in generated SQL\n" .
            "Seed: $seed\n" .
            "SQL: $sql\n" .
            "Classification: {$decision->classification}\n" .
            "Reason: {$decision->reason}\n" .
            "Phase: {$probeResult->phase->value}\n" .
            "SQLSTATE: " . ($probeResult->sqlState ?? 'unknown') . "\n" .
            "Error: " . ($probeResult->message ?? 'unknown')
        );
    }
}

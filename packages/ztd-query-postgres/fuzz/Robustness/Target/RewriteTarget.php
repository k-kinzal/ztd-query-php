<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Faker\Generator;
use Fuzz\Input\SqlInput;
use SqlFaker\PostgreSqlProvider;

/**
 * Executes the rewrite invariant target for coverage-guided fuzzing.
 */
final class RewriteTarget
{
    private readonly SqlInput $input;

    /**
     * Supplies deterministic SQL generation for this fuzz target.
     */
    public function __construct(Generator $faker, PostgreSqlProvider $provider)
    {
        $this->input = new SqlInput($faker, $provider);
    }

    /**
     * Checks the target invariants for one reproducible fuzzer input.
     * @throws Error
     */
    public function __invoke(string $input): void
    {
        (new RewriteScenario())->verify($this->input->rewrite($input), false);
    }
}

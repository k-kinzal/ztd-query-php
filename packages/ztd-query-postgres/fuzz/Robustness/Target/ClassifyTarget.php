<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Faker\Generator;
use Fuzz\Input\SqlInput;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use SqlFaker\PostgreSqlProvider;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;

/**
 * Executes the classify invariant target for coverage-guided fuzzing.
 */
final class ClassifyTarget
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
        $sql = $this->input->classify($input);
        $guard = new PgSqlQueryGuard(new PgSqlParser());
        foreach ([new ClassifyNeverThrowsChecker($guard), new ClassifyDeterministicChecker($guard)] as $checker) {
            $violation = $checker->check($sql);
            if ($violation !== null) {
                throw new Error((string) $violation);
            }
        }
    }
}

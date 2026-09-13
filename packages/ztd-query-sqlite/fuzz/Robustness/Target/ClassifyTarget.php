<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;

/**
 * Checks classification determinism using raw SQL and structural grammar mutations.
 */
final class ClassifyTarget
{
    private \Fuzz\Robustness\Input\SqlInput $input;

    /**
     * Retains immutable grammar planning; generated plans do not use Faker's later state.
     */
    public function __construct(\SqlFaker\SqliteProvider $provider)
    {
        $this->input = new \Fuzz\Robustness\Input\SqlInput($provider);
    }

    /**
     * Runs the contract in a fresh fixture environment for this input.
     */
    public function __invoke(string $input): void
    {
        $sql = $this->input->sql($input);
        FuzzBoundary::run('ClassifyTarget', $input, $sql, function () use ($sql): void {
            $guard = new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard(new \ZtdQuery\Platform\Sqlite\SqliteParser());
            $checkers = [
                new \Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker($guard),
                new \Fuzz\Robustness\Invariant\ClassifyDeterministicChecker($guard),
            ];
            foreach ($checkers as $checker) {
                $violation = $checker->check($sql);
                if ($violation !== null) {
                    throw new Error((string) $violation);
                }
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Fuzz\Robustness\Input\SqlInput;
use SqlFaker\SqliteProvider;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;

/**
 * Checks that arbitrary SQL can be classified without exceptions or state-dependent results.
 */
final class ClassifyTarget
{
    private readonly SqlInput $input;

    /**
     * Keeps SQLFaker grammar analysis outside the per-input callable.
     */
    public function __construct(SqliteProvider $provider)
    {
        $this->input = new SqlInput($provider);
    }

    /**
     * Runs classification twice on the same input and reports every unexpected failure.
     */
    public function __invoke(string $input): void
    {
        $sql = $this->input->sql($input);
        FuzzBoundary::run('classification', $input, $sql, static function () use ($sql): void {
            $guard = new SqliteQueryGuard(new SqliteParser());
            if ($guard->classify($sql) !== $guard->classify($sql)) {
                throw new Error('Classification changed for identical SQL');
            }
        });
    }
}

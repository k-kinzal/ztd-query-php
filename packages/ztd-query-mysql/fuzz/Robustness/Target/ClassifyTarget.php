<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;

/**
 * Checks deterministic classification of generated MySQL statements.
 */
final class ClassifyTarget
{
    /**
     * Classify twice without changing state and reject inconsistent results.
     *
     * @throws Error
     */
    public function __invoke(string $sql): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        if ($guard->classify($sql) !== $guard->classify($sql)) {
            throw new Error('Classification is not deterministic. SQL: ' . $sql);
        }
    }
}

<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

/**
 * Measures model construction and SQL reconstruction for this database package.
 */
#[Bench\Revs(10)]
#[Bench\Iterations(3)]
final class StatementBench
{
    /**
     * Parses and prints an INSERT through the database statement models.
     */
    public function benchRoundTrip(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $semantics->analyze('INSERT INTO items (id, name) VALUES (1, \'example\')')->toString();
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Node;

/**
 * One item of the list of RESET: the replica, the binary logs or (5.x) the query cache.
 *
 * The RESET statement that holds an item derives it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset.html.
 */
interface ResetTarget extends Node
{
    /**
     * Checks the release and reports a value the server refuses.
     */
    public function deriveTarget(Derivation $derivation): void;
}

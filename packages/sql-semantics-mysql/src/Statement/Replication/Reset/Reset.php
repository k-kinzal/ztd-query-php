<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `RESET item, …`: resets the replica, the binary logs or (5.x) the query cache.
 *
 * Mirrors SQLCOM_RESET with its REFRESH_* flags; the items are kept in
 * written order, an item possibly written twice. Rule: MYSQL-RESET-001. Each
 * item checks the release and reports refused values (ResetTarget). The
 * statement uses no table and provides no declaration.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Resetting the replica and the binary logs
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('reset replica, binary logs and gtids')->toString() // => 'RESET REPLICA, BINARY LOGS AND GTIDS'
 */
final class Reset implements Statement
{
    use Snapshot;

    /**
     * @var list<ResetTarget> The items in written order; at least one
     */
    public readonly array $targets;

    /**
     * @param list<ResetTarget> $targets The items in written order; at least one
     */
    public function __construct(array $targets)
    {
        $this->targets = Check::listOf($targets, ResetTarget::class, 'RESET names a list of items.', 1);
    }

    /**
     * Derives every item.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->targets as $target) {
            $target->deriveTarget($derivation);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RESET')->list($this->targets);
    }
}

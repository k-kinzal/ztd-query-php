<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `RESET item, …`: resets the replica, the binary logs or (5.x) the query cache.
 *
 * The items are kept in written order, an item possibly written twice.
 * effectiveTargets() combines repeated targets: the last explicit binary-log
 * index and the last replica options apply. Rule: MYSQL-RESET-001. Each
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
     * @var non-empty-list<ResetTarget> The items in written order; at least one
     */
    public readonly array $targets;

    /**
     * @param list<ResetTarget> $targets The items in written order; at least one
     */
    public function __construct(array $targets)
    {
        $this->targets = Check::listOf($targets, ResetTarget::class, 'RESET names a list of items.', 1);
        $terms = [];
        foreach ($this->targets as $target) {
            if ($target instanceof ResetBinaryLogs || $target instanceof ResetReplica) {
                Check::input(!isset($terms[$target::class]) || $terms[$target::class] === $target->terminology, 'Repeated RESET targets use one vocabulary for each target kind.');
                $terms[$target::class] = $target->terminology;
            }
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
     * Combines the options of repeated targets, with binary logs before the replica.
     *
     * An omitted TO keeps the last explicit index. An omitted channel or ALL
     * replaces the preceding replica option. Written targets remain available
     * for diagnostics, including invalid options overridden by a later target.
     * Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
     *
     * @return list<ResetTarget> At most one target of each kind
     */
    public function effectiveTargets(): array
    {
        $logs = null;
        $replica = null;
        $cache = null;
        foreach ($this->targets as $target) {
            if ($target instanceof ResetBinaryLogs && ($logs === null || $target->first !== null)) {
                $logs = $target;
            } elseif ($target instanceof ResetReplica) {
                $replica = $target;
            } elseif ($target instanceof ResetQueryCache) {
                $cache = $target;
            }
        }

        return array_values(array_filter([$logs, $replica, $cache], static fn (?ResetTarget $target): bool => $target !== null));
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RESET')->list($this->targets);
    }
}

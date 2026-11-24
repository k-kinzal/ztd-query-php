<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Performs up to the specified native reclamation steps on one resolved database.
 *
 * @visibility public
 * @example Applying the default native limit
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum())->toString() // => 'PRAGMA "main".incremental_vacuum'
 */
final class IncrementalVacuum implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the target and the effective reclamation limit.
     */
    public function __construct(
        public readonly Identifier $schema = new Identifier('main'),
        public readonly PageReclaimLimit $limit = new PageReclaimLimit(),
    ) {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the reclamation bound, with the native shorthand for the maximum.
     */
    public function toString(): string
    {
        $argument = $this->limit->pages === PageReclaimLimit::MAXIMUM ? '' : '(' . $this->limit->pages . ')';

        return 'PRAGMA ' . $this->schema->quoted() . '.incremental_vacuum' . $argument;
    }
}

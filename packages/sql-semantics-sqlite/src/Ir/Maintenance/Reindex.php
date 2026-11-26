<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite index rebuild operation.
 *
 * @visibility public
 * @example Rebuilding indexes that use the native NOCASE collation
 *     $target = \SqlSemantics\Platform\Sqlite\Ir\Collation::NoCase;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex($target))->toString() // => 'REINDEX "NOCASE"'
 */
final class Reindex implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the selector. The null case selects all native indexes.
     */
    public function __construct(public readonly Collation|RelationTarget|IndexTarget|null $target = null)
    {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the rebuild with its resolved selector.
     */
    public function toString(): string
    {
        return match (true) {
            $this->target === null => 'REINDEX',
            $this->target instanceof Collation => 'REINDEX ' . (new Identifier($this->target->value))->quoted(),
            $this->target instanceof RelationTarget, $this->target instanceof IndexTarget => 'REINDEX ' . $this->target->quoted(),
        };
    }
}

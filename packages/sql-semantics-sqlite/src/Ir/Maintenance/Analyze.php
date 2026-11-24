<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite statistics collection statement.
 *
 * @visibility public
 * @example Gathering statistics in the temporary schema
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze($target))->toString() // => 'ANALYZE "temp"'
 */
final class Analyze implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the statistics scope. The null case analyzes every non-temp schema.
     */
    public function __construct(public readonly Identifier|RelationTarget|IndexTarget|null $target = null)
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
     * Emits the native statistics operation and its exact selector.
     */
    public function toString(): string
    {
        return $this->target === null ? 'ANALYZE' : 'ANALYZE ' . $this->target->quoted();
    }
}

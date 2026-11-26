<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Reads or changes spilling for a connection and its selected pager.
 *
 * @visibility public
 * @example Turning off cache spill for the connection
 *     $schema = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main');
 *     $off = \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode::Disabled;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill($schema, $off))->toString() // => 'PRAGMA "main".cache_spill(OFF)'
 */
final class CacheSpill implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the resolved pager and the exact connection and local change.
     */
    public function __construct(
        public readonly Identifier $schema = new Identifier('main'),
        public readonly SpillMode|SpillThreshold|null $change = null,
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
     * Emits the switch, joint modification or getter for the selected pager.
     */
    public function toString(): string
    {
        $change = $this->change;
        $argument = match (true) {
            $change === null => '',
            $change instanceof SpillMode => '(' . $change->value . ')',
            $change instanceof SpillThreshold => '(' . $change->sqlArgument() . ')',
        };

        return 'PRAGMA ' . $this->schema->quoted() . '.cache_spill' . $argument;
    }
}

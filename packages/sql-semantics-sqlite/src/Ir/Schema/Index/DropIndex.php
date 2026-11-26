<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Index;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved SQLite index removal or guarded absence.
 *
 * @visibility public
 * @example Removing an index if it exists in the selected schema
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('by date');
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex($name);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex($target, true))->toString() // => 'DROP INDEX IF EXISTS "by date"'
 */
final class DropIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the target's presence role against the native missing-object policy.
     */
    public function __construct(
        public readonly IndexReference|MissingIndex $target,
        public readonly bool $ifExists = false,
    ) {
        Check::input($ifExists || $target instanceof IndexReference, 'An absent DROP INDEX target requires IF EXISTS.');
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the resolved drop and its native missing-object policy.
     */
    public function toString(): string
    {
        return 'DROP INDEX ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->target->quoted();
    }
}

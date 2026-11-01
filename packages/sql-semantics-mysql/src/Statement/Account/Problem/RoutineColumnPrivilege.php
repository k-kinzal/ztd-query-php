<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A completed GRANT applies a column privilege to a routine kind.
 *
 * Modern servers refuse this when the grant is read, including inside routines.
 * Legacy servers first refuse a routine kind without an object privilege level.
 * The position identifies the completed operand, not retained input text.
 *
 * @visibility public
 * @example Describing the intrinsic privilege mismatch
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege(12, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel()))->message() // => 'Column privileges apply only to tables.'
 */
final class RoutineColumnPrivilege implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The exclusive end of the completed grant
     * @param \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel $level The typed target, retaining its current-database dependency
     */
    public function __construct(public readonly int $offset, public readonly \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel $level)
    {
        Check::input($offset >= 0, 'A completed grant ends at a nonnegative offset.');
    }

    /**
     * Describes the structural mismatch independently of the server's diagnostic spelling.
     */
    public function message(): string
    {
        return 'Column privileges apply only to tables.';
    }
}

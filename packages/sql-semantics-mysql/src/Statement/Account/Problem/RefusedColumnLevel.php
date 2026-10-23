<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A completed column privilege target that cannot name columns.
 *
 * Legacy GRANT and REVOKE refuse this target before reading recipients. The
 * descriptor therefore retains only completed operands and their declaration
 * scope; neither a fabricated recipient nor a complete request is needed.
 *
 * @visibility public
 * @example Describing a column privilege outside a table
 *     $privilege = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [new \SqlSemantics\Statement\Identifier\Name('a')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel($privilege, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel()))->message() // => 'A column privilege requires an individual table target.'
 */
final class RefusedColumnLevel implements Diagnostic
{
    use Snapshot;

    /**
     * @param StaticPrivilege $privilege A privilege with at least one column
     * @param CurrentDatabaseLevel|DatabaseLevel|GlobalLevel $level The completed non-table target
     * @param QualifiedName|null $program The enclosing stored declaration, when present
     */
    public function __construct(public readonly StaticPrivilege $privilege, public readonly CurrentDatabaseLevel|DatabaseLevel|GlobalLevel $level, public readonly ?QualifiedName $program = null)
    {
        Check::input($privilege->columns !== [], 'A refused column target needs a column privilege.');
    }

    /**
     * Describes the contradiction without retaining the original input.
     */
    public function message(): string
    {
        return 'A column privilege requires an individual table target.';
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP RESOURCE GROUP name [FORCE]` (8.0+).
 *
 * Mirrors PT_drop_resource_group. Rule: MYSQL-RESOURCE-GROUP-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-resource-group.html. Status: Implemented.
 *
 * @visibility public
 * @example Dropping a group
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('drop resource group batch force')->statement->force // => true
 */
final class DropResourceGroup implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The group name
     * @param bool $force Whether FORCE is written
     */
    public function __construct(public readonly Name $name, public readonly bool $force = false)
    {
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'RESOURCE', 'GROUP')->name($this->name, NameUse::Label);
        if ($this->force) {
            $out->keyword('FORCE');
        }
    }
}

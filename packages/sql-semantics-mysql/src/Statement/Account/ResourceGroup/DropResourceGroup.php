<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `DROP RESOURCE GROUP name [FORCE]` (8.0+).
 *
 * Mirrors PT_drop_resource_group. Rule: MYSQL-RESOURCE-GROUP-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-resource-group.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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

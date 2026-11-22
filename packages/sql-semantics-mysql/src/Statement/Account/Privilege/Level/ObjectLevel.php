<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `ON [TABLE|FUNCTION|PROCEDURE] [db.]name`: one table or stored routine.
 *
 * For a table the statement records the resolution of the name as the
 * relation fact of this node; a stored routine is not declared in a
 * context, so no fact is recorded for it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-table-privileges,
 * https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-routine-privileges.
 *
 * @visibility SqlSemantics
 */
final class ObjectLevel implements PrivilegeLevel
{
    use Snapshot;

    /**
     * @param QualifiedName $name The object name with its optional database
     */
    public function __construct(public readonly QualifiedName $name)
    {
        Check::input($name->catalog === null, 'An object is qualified by at most a database.');
    }

    /**
     * Describes the level.
     */
    public function describe(): string
    {
        return 'object ' . ($this->name->schema === null ? '' : $this->name->schema->value . '.') . $this->name->name->value;
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->glue()->symbol('.')->glue();
        }
        $out->name($this->name->name, NameUse::Relation);
    }
}

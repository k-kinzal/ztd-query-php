<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A privilege the server or a component registers at runtime, such as `BACKUP_ADMIN` (8.0+).
 *
 * Mirrors PT_role_or_dynamic_privilege read as a privilege, and
 * PT_dynamic_privilege for the form written with a column list. The server
 * accepts the column list in the grammar and ignores it; it is kept as
 * written. A dynamic privilege can only be granted globally
 * (ER_ILLEGAL_PRIVILEGE_LEVEL otherwise). Which names are registered depends
 * on the server and its components, which a declaration context does not
 * hold.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html#privileges-provided-dynamic.
 *
 * @visibility public
 * @example Reading a dynamic privilege
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT BACKUP_ADMIN ON *.* TO u')->statement->privileges[0]->name->value // => 'BACKUP_ADMIN'
 */
final class DynamicPrivilege implements Grantable
{
    use Snapshot;

    /**
     * @var list<Name> The column list as written; none when absent
     */
    public readonly array $columns;

    /**
     * @param Name $name The privilege name as written
     * @param list<Name> $columns The column list as written; none when absent
     */
    public function __construct(public readonly Name $name, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'A column list holds column names.');
    }

    /**
     * Writes the privilege and its column list.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label);
        if ($this->columns !== []) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
    }
}

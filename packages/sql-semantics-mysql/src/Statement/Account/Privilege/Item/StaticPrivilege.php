<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A privilege the server defines statically, such as `SELECT (a, b)` or `CREATE ROUTINE`, with its column list.
 *
 * Mirrors PT_static_privilege. Only SELECT, INSERT, UPDATE and REFERENCES
 * take a column list; the columns are kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-column-privileges.
 *
 * @visibility public
 * @example Reading a column privilege
 *     $privilege = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT SELECT (a, b) ON t TO u')->statement->privileges[0];
 *     [$privilege->kind, count($privilege->columns)] // => [\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, 2]
 */
final class StaticPrivilege implements Grantable
{
    use Snapshot;

    /**
     * @var list<Name> The columns the privilege is limited to; none for the whole object
     */
    public readonly array $columns;

    /**
     * @param PrivilegeKind $kind The privilege
     * @param list<Name> $columns The columns the privilege is limited to; none for the whole object
     */
    public function __construct(public readonly PrivilegeKind $kind, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'A column list holds column names.');
        Check::input($this->columns === [] || $kind->columns(), 'Only SELECT, INSERT, UPDATE and REFERENCES take a column list.');
    }

    /**
     * Writes the privilege and its column list.
     */
    public function render(Output $out): void
    {
        $out->keyword(...$this->kind->words());
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

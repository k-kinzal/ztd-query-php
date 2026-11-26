<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A privilege the server defines statically, such as `SELECT (a, b)` or `CREATE ROUTINE`, with its column list.
 *
 * Mirrors PT_static_privilege. Only SELECT, INSERT, UPDATE and REFERENCES
 * take a column list; the columns are kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-column-privileges.
 *
 * @visibility SqlSemantics
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

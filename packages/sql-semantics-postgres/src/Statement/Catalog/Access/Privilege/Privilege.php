<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One privilege of a GRANT or REVOKE, optionally limited to columns; in a role grant, the granted role.
 *
 * Mirrors `AccessPriv`: a privilege name and a column list. The name is one
 * of the keyword spellings or an identifier such as `insert` or `usage`;
 * ALL with a column list is the privilege without a name. The grammar reads
 * the roles of GRANT role TO role with the same production, so there the
 * name is the role name, and a keyword spelling names the role of that word.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility SqlSemantics
 * @example Rejecting ALL without a column list as a privilege of its own
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class Privilege implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The columns the privilege is limited to; empty when no column list is written
     */
    public readonly array $columns;

    /**
     * @param PrivilegeKeyword|Name $name The keyword spelling or the identifier that names the privilege
     * @param list<Name> $columns The columns the privilege is limited to; empty when no column list is written
     */
    public function __construct(public readonly PrivilegeKeyword|Name $name, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'The columns of a privilege are names.');
        Check::input($name !== PrivilegeKeyword::All || $this->columns !== [], 'ALL is a privilege of its own only with a column list.');
        Check::input($name !== PrivilegeKeyword::AlterSystem || $this->columns === [], 'ALTER SYSTEM takes no column list.');
    }

    /**
     * Answers the privilege name the server receives, or null for ALL.
     */
    public function privilege(): ?string
    {
        return $this->name instanceof Name ? $this->name->value : $this->name->privilege();
    }

    /**
     * Writes the name and the column list, if any.
     */
    public function render(Output $out): void
    {
        if ($this->name instanceof Name) {
            $out->name($this->name, NameUse::Column);
        } else {
            $out->keyword(...explode(' ', $this->name->value));
        }
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

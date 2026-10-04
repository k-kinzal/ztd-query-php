<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP ROLE [IF EXISTS] role, …` (8.0+): a request to remove roles.
 *
 * Mirrors PT_drop_role. Rule: MYSQL-DROP-ROLE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-role.html. Status: Implemented.
 *
 * @visibility public
 * @example Dropping a role
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('drop role if exists reader')->toString() // => 'DROP ROLE IF EXISTS reader'
 */
final class DropRole implements Statement
{
    use Snapshot;

    /**
     * @var list<AccountName> The roles in order
     */
    public readonly array $roles;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<AccountName> $roles The roles in order; at least one
     */
    public function __construct(public readonly bool $ifExists, array $roles)
    {
        $this->roles = Check::listOf($roles, AccountName::class, 'DROP ROLE names at least one role.', 1);
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
        $out->keyword('DROP', 'ROLE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->list($this->roles);
    }
}

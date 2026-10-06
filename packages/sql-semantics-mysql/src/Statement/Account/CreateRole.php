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
 * `CREATE ROLE [IF NOT EXISTS] role, …` (8.0+): a request to create roles, which are locked accounts.
 *
 * Mirrors PT_create_role. Rule: MYSQL-CREATE-ROLE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-role.html. Status: Implemented.
 *
 * @visibility public
 * @example Creating roles
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create role if not exists reader, 'writer'@'%'")->toString() // => 'CREATE ROLE IF NOT EXISTS reader, writer@`%`'
 */
final class CreateRole implements Statement
{
    use Snapshot;

    /**
     * @var list<AccountName> The roles in order
     */
    public readonly array $roles;

    /**
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param list<AccountName> $roles The roles in order; at least one
     */
    public function __construct(public readonly bool $ifNotExists, array $roles)
    {
        $this->roles = Check::listOf($roles, AccountName::class, 'CREATE ROLE names at least one role.', 1);
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
        $out->keyword('CREATE', 'ROLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->list($this->roles);
    }
}

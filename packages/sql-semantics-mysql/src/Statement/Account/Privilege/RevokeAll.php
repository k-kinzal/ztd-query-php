<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `REVOKE [IF EXISTS] ALL [PRIVILEGES], GRANT OPTION FROM accounts [IGNORE UNKNOWN USER]`: a request to revoke every privilege at every level.
 *
 * Mirrors SQLCOM_REVOKE_ALL. Rule: MYSQL-REVOKE-ALL-001. The statement names
 * no level and no object, so it has no relation fact; it revokes the
 * privileges and role grants of the accounts, not the accounts. PRIVILEGES is
 * an optional word.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility public
 * @example Revoking everything
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('REVOKE ALL PRIVILEGES, GRANT OPTION FROM u')->toString() // => 'REVOKE ALL, GRANT OPTION FROM u'
 */
final class RevokeAll implements Statement
{
    use Snapshot;

    /**
     * @var list<UserSpecification> The accounts
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<UserSpecification> $users The accounts; at least one
     * @param bool $ignoreUnknownUser Whether IGNORE UNKNOWN USER is written
     */
    public function __construct(public readonly bool $ifExists, array $users, public readonly bool $ignoreUnknownUser = false)
    {
        $this->users = Check::listOf($users, UserSpecification::class, 'REVOKE names at least one account.', 1);
        foreach ($this->users as $user) {
            Check::input($user->granted(), 'A REVOKE account is a named account with at most one authentication method.');
        }
    }

    /**
     * Derives nothing: the statement names no object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REVOKE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->keyword('ALL')->symbol(',')->keyword('GRANT', 'OPTION', 'FROM')->list($this->users);
        if ($this->ignoreUnknownUser) {
            $out->keyword('IGNORE', 'UNKNOWN', 'USER');
        }
    }
}

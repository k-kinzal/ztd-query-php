<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

/**
 * `REVOKE [IF EXISTS] ALL [PRIVILEGES], GRANT OPTION FROM accounts [IGNORE UNKNOWN USER]`: a request to revoke every privilege at every level.
 *
 * Mirrors SQLCOM_REVOKE_ALL. Rule: MYSQL-REVOKE-ALL-001. The statement names
 * no level and no object, so it has no relation fact; it revokes the
 * privileges and role grants of the accounts, not the accounts. PRIVILEGES is
 * an optional word.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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

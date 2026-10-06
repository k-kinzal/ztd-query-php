<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `REVOKE [IF EXISTS] privileges ON [kind] level FROM accounts [IGNORE UNKNOWN USER]`: a request to revoke privileges.
 *
 * Mirrors the SQLCOM_REVOKE path of LEX (grant_if_exists,
 * ignore_unknown_user, 8.0.30+). Rule: MYSQL-REVOKE-001. The level and its
 * table are derived by MYSQL-PRIVILEGE-CHECKS-001; REVOKE does not require
 * the table to exist, and under IF EXISTS a dynamic privilege at another
 * level is only a warning. MySQL 5.6 writes the account list of GRANT here,
 * with an optional IDENTIFIED clause per account.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility public
 * @example Revoking a privilege
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('revoke if exists execute on procedure p from u ignore unknown user')->toString() // => 'REVOKE IF EXISTS EXECUTE ON PROCEDURE p FROM u IGNORE UNKNOWN USER'
 */
final class RevokePrivileges implements Statement
{
    use Snapshot;

    /**
     * @var list<Grantable> The revoked privileges in order; ALL stands alone
     */
    public readonly array $privileges;

    /**
     * @var list<UserSpecification> The accounts privileges are revoked from
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<Grantable> $privileges The revoked privileges in order; ALL stands alone
     * @param ObjectKind $kind The kind of object the level names
     * @param PrivilegeLevel $level The privilege level
     * @param list<UserSpecification> $users The accounts privileges are revoked from; at least one
     * @param bool $ignoreUnknownUser Whether IGNORE UNKNOWN USER is written
     */
    public function __construct(
        public readonly bool $ifExists,
        array $privileges,
        public readonly ObjectKind $kind,
        public readonly PrivilegeLevel $level,
        array $users,
        public readonly bool $ignoreUnknownUser = false,
    ) {
        $this->privileges = Check::listOf($privileges, Grantable::class, 'REVOKE names at least one privilege.', 1);
        $this->users = Check::listOf($users, UserSpecification::class, 'REVOKE names at least one account.', 1);
        foreach ($this->privileges as $privilege) {
            Check::input(!$privilege instanceof AllPrivileges || count($this->privileges) === 1, 'ALL is the only item of its list.');
            Check::input(!$privilege instanceof GrantedRole || $privilege->role->host !== null, 'A role without a host in a privilege list reads as a dynamic privilege.');
        }
        foreach ($this->users as $user) {
            Check::input($user->granted(), 'A REVOKE account is a named account with at most one authentication method.');
        }
    }

    /**
     * Derives the level and the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->revoke($derivation, $this->privileges, $this->kind, $this->level, $this->ifExists);
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
        $out->list($this->privileges)->keyword('ON');
        $keyword = $this->kind->keyword();
        if ($keyword !== null) {
            $out->keyword($keyword);
        }
        $out->node($this->level)->keyword('FROM')->list($this->users);
        if ($this->ignoreUnknownUser) {
            $out->keyword('IGNORE', 'UNKNOWN', 'USER');
        }
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\PrivilegeChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete stored REVOKE whose routine target has a wildcard level.
 *
 * The definition retains every privilege and recipient without binding them.
 * Invocation resolves the current database and account spelling before the
 * target condition. Global ALL uses this role only in legacy MySQL; modern SQL
 * instead gives it an ordinary global-revocation interpretation.
 *
 * @visibility public
 * @example Keeping a stored target condition outside ordinary statements
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE ALL ON PROCEDURE * FROM u');
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges // => true
 */
final class DeferredRevokePrivileges implements ProgramStatement, PrivilegeChange
{
    use Snapshot;

    /**
     * @var non-empty-list<Privilege> The revoked privileges in order; ALL stands alone
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<UserSpecification> The accounts privileges are revoked from
     */
    public readonly array $users;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<Privilege> $privileges The revoked privileges in order; ALL stands alone
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
        $this->privileges = Check::listOf($privileges, Privilege::class, 'REVOKE names at least one privilege.', 1);
        $this->users = Check::listOf($users, UserSpecification::class, 'REVOKE names at least one account.', 1);
        foreach ($this->privileges as $privilege) {
            Check::input($kind === ObjectKind::Table || !$privilege instanceof Item\StaticPrivilege || $privilege->columns === [], 'Column privileges apply only to tables.');
            Check::input(!$privilege instanceof AllPrivileges || count($this->privileges) === 1, 'ALL is the only item of its list.');
        }
        foreach ($this->users as $user) {
            Check::input($user->granted(), 'A REVOKE account is a named account with at most one authentication method.');
            Check::input($user->identification === null, 'A REVOKE account has no authentication clause.');
        }
        $scope = new \SqlSemantics\Platform\MySql\Rules\Account\Privilege\RevokeScopes();
        Check::input($scope->deferred($this->privileges, $kind, $level, true), 'A deferred revocation retains a wildcard routine target.');
        Check::input(!$scope->globalAll($this->privileges, $kind, $level) || (!$ifExists && !$ignoreUnknownUser), 'A legacy global routine revocation cannot use modern account flags.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Keeps target and account binding at invocation, after the containing definition.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
    }

    /**
     * Reports the complete retained target condition without source input.
     */
    public function problem(): \SqlSemantics\Platform\MySql\Statement\Account\Problem\Privilege\RefusedRevokeLevel
    {
        return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\Privilege\RefusedRevokeLevel($this);
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

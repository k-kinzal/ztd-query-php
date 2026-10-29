<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

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
 * @example Refusing column privileges on a routine
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges(false, [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [new \SqlSemantics\Statement\Identifier\Name('a')])], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p'))), [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Revoking a privilege
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('revoke if exists execute on procedure p from u ignore unknown user')->toString() // => 'REVOKE IF EXISTS EXECUTE ON PROCEDURE p FROM u IGNORE UNKNOWN USER'
 */
final class RevokePrivileges extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, PrivilegeChange, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
     * @param bool $tableKeyword Whether TABLE is explicitly written for a table revocation
     */
    public function __construct(
        public readonly bool $ifExists,
        array $privileges,
        public readonly ObjectKind $kind,
        public readonly PrivilegeLevel $level,
        array $users,
        public readonly bool $ignoreUnknownUser = false,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly bool $tableKeyword = false
    ) {
        Check::input(!$tableKeyword || $kind === ObjectKind::Table, 'TABLE names a table-level privilege object.');
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
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
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Identifies a wildcard routine target refused under the selected release.
     */
    public function hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease $release): bool
    {
        $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        return (new \SqlSemantics\Platform\MySql\Rules\Account\Privilege\RevokeScopes())->deferred($this->privileges, $this->kind, $this->level, $legacy);
    }

    /**
     * Derives the level and the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->notices($this->privileges, $derivation);
        $legacy = in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $problem = $legacy ? (new PrivilegeChecks())->columnLevel($this->privileges, $this->level) : null;
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $this->level);
            return;
        }
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, $this->users);
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
        $keyword = $this->tableKeyword ? 'TABLE' : $this->kind->keyword();
        if ($keyword !== null) {
            $out->keyword($keyword);
        }
        $out->node($this->level)->keyword('FROM')->list($this->users);
        if ($this->ignoreUnknownUser) {
            $out->keyword('IGNORE', 'UNKNOWN', 'USER');
        }
    }
}

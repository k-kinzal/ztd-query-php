<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use LogicException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\DropRole;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\SetDefaultRole;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Account\SetRole;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCreateUser;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Statement\Statement;

/**
 * Reads the account names of an account statement as the server does.
 *
 * The server checks each name while it parses the statement, in the order written: a user name
 * of more than 32 characters or a host of more than 255 bytes is ER_WRONG_STRING_LENGTH, which
 * quotes the first 70 bytes of the name, and a host with `@` is a malformed hostname. A name
 * without a host names `%`; CURRENT_USER and USER() name the account of the session. A host is
 * kept in an ASCII column of the grant tables, so a host with another character draws
 * ER_TRUNCATED_WRONG_VALUE_FOR_FIELD for each grant table the statement writes or reads. GRANT
 * and REVOKE of privileges warn that a host name, a host that is neither an address nor a
 * pattern, cannot match under skip_name_resolve, which the server runs with (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html.
 *
 * @visibility MySqlMemory
 */
final class Names
{
    /**
     * @param GrammarRelease $release The release whose limits the names follow
     */
    public function __construct(public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
    }

    /**
     * Reads account operands in written order without looking up account state.
     *
     * @param list<Account> $accounts The written account operands
     * @param bool $notices Whether reading notices should be emitted
     */
    public function read(array $accounts, Session $session, bool $notices = true): void
    {
        foreach ($accounts as $account) {
            if ($notices && $account instanceof AccountName) {
                foreach ($account->warnings($this->release) as $warning) {
                    $session->diagnostics->warning($warning->code(), $warning->message());
                }
            }
            $this->check([$account]);
        }
    }

    /**
     * Tells whether a statement is executed by an account command, which raises the problems of the statement itself, in the order of the server.
     */
    public static function owns(Statement $statement): bool
    {
        return $statement instanceof CreateUser || $statement instanceof CreateRole || $statement instanceof DropUser || $statement instanceof DropRole
            || $statement instanceof AlterUser || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords || $statement instanceof AlterDefaultRole || $statement instanceof RenameUser || $statement instanceof SetPassword
            || $statement instanceof GrantPrivileges || $statement instanceof GrantRoles || $statement instanceof GrantProxy || $statement instanceof RevokePrivileges
            || $statement instanceof RevokeRoles || $statement instanceof RevokeProxy || $statement instanceof RevokeAll || $statement instanceof SetRole
            || $statement instanceof SetDefaultRole || $statement instanceof ShowGrants || $statement instanceof ShowCreateUser;
    }

    /**
     * Answers the longest user name and host name the release takes: 32 and 255 characters, 16 and 60 in MySQL 5.6, 32 and 60 in 5.7 (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @return array{int, int}
     */
    public function limits(): array
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->limits($this->release);
    }

    /**
     * Checks the names of a statement, in the order written.
     *
     * MySQL 5.6 checks both original operands before trimming when the host is
     * explicit; with no host it checks the trimmed user (verified on 5.6.51).
     *
     * @param list<Account|SessionUser|null> $names
     *
     * @throws SqlError When a name is too long or its host is malformed
     */
    public function check(array $names): void
    {
        foreach ($names as $name) {
            if (!$name instanceof AccountName) {
                continue;
            }
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->problem($name->user, $name->host, $this->release);
            if ($problem !== null) {
                throw $this->refusal($problem);
            }
        }
    }

    /**
     * Formats a typed account-name refusal from input lowering or profile interpretation.
     */
    public function refusal(\SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure $problem): SqlError
    {
        if ($problem->limit === null) {
            return new SqlError(StatementError::UnknownError, "Malformed hostname (illegal symbol: '@')");
        }
        return DataError::WrongStringLength->error($this->cut($problem->operand), $problem->host ? 'host name' : 'user name', $problem->limit);
    }

    /**
     * Answers the first 70 bytes of a name, as ER_WRONG_STRING_LENGTH quotes it; a character cut in two is written `?`.
     *
     * @example A long name
     *     (new \MySqlMemory\Command\Account\Names())->cut(str_repeat('a', 80)) // => str_repeat('a', 70)
     */
    public function cut(string $name): string
    {
        return (new \MySqlMemory\Error\DiagnosticText())->bytes(explode("\0", $name, 2)[0], 70, in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true));
    }

    /**
     * Formats the bounded account list of ER_CANNOT_USER.
     * Native observations retain at most 256 bytes of the displayed operand.
     */
    public function failure(string $operation, string $names): SqlError
    {
        return AccountError::CannotUser->error($operation, (new \MySqlMemory\Error\DiagnosticText())->bytes($names, 256, in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)));
    }

    /**
     * Answers the account each alteration of a statement names.
     *
     * @param list<UserAlteration|Grantee> $users
     * @return list<Account|SessionUser>
     */
    public function users(array $users): array
    {
        return array_map(static fn (UserAlteration|Grantee $user): Account|SessionUser => match (true) {
            $user instanceof UserSpecification, $user instanceof FactorChange, $user instanceof Grantee => $user->user,
            default => throw new LogicException('An account alteration names an account.'),
        }, $users);
    }

    /**
     * Answers the account a name names.
     */
    public function identity(Account|SessionUser $name, Session $session): Identity
    {
        if ($name instanceof AccountName) {
            return Identity::of($name->username(), $name->hostname());
        }

        return $session->variables->definer;
    }

    /**
     * Records the ER_TRUNCATED_WRONG_VALUE_FOR_FIELD warnings of a host with a character the modern grant tables cannot hold.
     * MySQL 5.6 and 5.7 retain Unicode hosts without ASCII conversion warnings.
     *
     * @param int $tables The number of grant tables the statement writes or reads
     * @param string $column The grant table column label, Host or HOST for dynamic privileges
     */
    public function ascii(Identity $identity, int $tables, Diagnostics $diagnostics, string $column = 'Host'): void
    {
        if (in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) || preg_match('/[\x80-\xFF]/', $identity->host, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }
        $rest = substr($identity->host, $match[0][1]);
        $quoted = (string) preg_replace_callback('/[^\x20-\x7E]/', static fn (array $byte): string => sprintf('\\x%02X', ord($byte[0])), substr($rest, 0, 6));
        for ($i = 0; $i < $tables; $i++) {
            $diagnostics->warning(DataError::TruncatedWrongValueForField, DataError::TruncatedWrongValueForField->message('string', $quoted . (strlen($rest) > 6 ? '...' : ''), $column, 1));
        }
    }

    /**
     * Answers the note that an account does not exist (ER_USER_DOES_NOT_EXIST), or with $exists that it exists (ER_USER_ALREADY_EXISTS): MySQL 5.7 names it a user, later releases an authorization ID (verified on live 5.7.44 and 8.4 servers).
     */
    public function existence(Identity $identity, bool $exists): string
    {
        $error = $exists ? AccountError::UserAlreadyExists : AccountError::UserDoesNotExist;
        if ($this->release !== GrammarRelease::MySql5744) {
            return $error->message($identity->quoted());
        }

        return 'User ' . $identity->quoted() . ($exists ? ' already exists.' : ' does not exist.');
    }

    /**
     * Records the warning that a host name cannot match when names are not resolved; MySQL 5.6 does not warn of `localhost` (verified on a live 5.6.51 server).
     */
    public function resolve(Identity $identity, Diagnostics $diagnostics): void
    {
        $host = $identity->host;
        if ($host === '' || ($this->release === GrammarRelease::MySql5651 && strtolower($host) === 'localhost') || strpbrk($host, '%_:') !== false || preg_match('/\A[0-9.\/]+\z/', $host) === 1) {
            return;
        }
        $diagnostics->warning(AccountError::HostnameWontWork, AccountError::HostnameWontWork->message());
    }
}

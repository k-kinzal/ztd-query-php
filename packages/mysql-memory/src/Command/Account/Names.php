<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use LogicException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Error\AccountError;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
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
     * Tells whether a statement is executed by an account command, which raises the problems of the statement itself, in the order of the server.
     */
    public static function owns(Statement $statement): bool
    {
        return $statement instanceof CreateUser || $statement instanceof CreateRole || $statement instanceof DropUser || $statement instanceof DropRole
            || $statement instanceof AlterUser || $statement instanceof AlterDefaultRole || $statement instanceof RenameUser || $statement instanceof SetPassword
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
        return match (true) {
            $this->release === GrammarRelease::MySql5651 => [16, 60],
            $this->release === GrammarRelease::MySql5744 => [32, 60],
            default => [32, 255],
        };
    }

    /**
     * Checks the names of a statement, in the order written.
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
            $user = $name->user->value;
            [$users, $hosts] = $this->limits();
            if (mb_strlen($user, 'UTF-8') > $users) {
                throw DataError::WrongStringLength->error($this->cut($user), 'user name', $users);
            }
            $host = $name->host->value ?? '%';
            if (strlen($host) > $hosts) {
                throw DataError::WrongStringLength->error($this->cut($host), 'host name', $hosts);
            }
            if (str_contains($host, '@')) {
                throw new SqlError(StatementError::UnknownError, "Malformed hostname (illegal symbol: '@')");
            }
        }
    }

    /**
     * Answers the first 70 bytes of a name, as ER_WRONG_STRING_LENGTH quotes it; a character cut in two is written `?`.
     *
     * @example A long name
     *     (new \MySqlMemory\Command\Account\Names())->cut(str_repeat('a', 80)) // => str_repeat('a', 70)
     */
    public function cut(string $name): string
    {
        $cut = mb_strcut($name, 0, 70, 'UTF-8');

        return strlen($name) > 70 && strlen($cut) < 70 ? $cut . '?' : $cut;
    }

    /**
     * Answers the account each alteration of a statement names.
     *
     * @param list<UserAlteration> $users
     * @return list<Account|SessionUser>
     */
    public function users(array $users): array
    {
        return array_map(static fn (UserAlteration $user): Account|SessionUser => match (true) {
            $user instanceof UserSpecification, $user instanceof FactorChange => $user->user,
            default => throw new LogicException('An account alteration names an account.'),
        }, $users);
    }

    /**
     * Answers the account a name names.
     */
    public function identity(Account|SessionUser $name, Session $session): Identity
    {
        if ($name instanceof AccountName) {
            return Identity::of($name->user->value, $name->host?->value);
        }

        return new Identity($session->user, '%');
    }

    /**
     * Records the ER_TRUNCATED_WRONG_VALUE_FOR_FIELD warnings of a host with a character the grant tables cannot hold.
     *
     * @param int $tables The number of grant tables the statement writes or reads
     */
    public function ascii(Identity $identity, int $tables, Diagnostics $diagnostics): void
    {
        if (preg_match('/[\x80-\xFF]/', $identity->host, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }
        $rest = substr($identity->host, $match[0][1]);
        $quoted = (string) preg_replace_callback('/[^\x20-\x7E]/', static fn (array $byte): string => sprintf('\\x%02X', ord($byte[0])), substr($rest, 0, 6));
        for ($i = 0; $i < $tables; $i++) {
            $diagnostics->warning(DataError::TruncatedWrongValueForField, DataError::TruncatedWrongValueForField->message('string', $quoted . (strlen($rest) > 6 ? '...' : ''), 'Host', 1));
        }
    }

    /**
     * Records the warning that a host name cannot match when names are not resolved.
     */
    public function resolve(Identity $identity, Diagnostics $diagnostics): void
    {
        $host = $identity->host;
        if ($host === '' || strpbrk($host, '%_:') !== false || preg_match('/\A[0-9.\/]+\z/', $host) === 1) {
            return;
        }
        $diagnostics->warning(AccountError::HostnameWontWork, AccountError::HostnameWontWork->message());
    }
}

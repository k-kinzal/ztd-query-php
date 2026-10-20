<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Identity;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

/**
 * Grants and revokes permission to act as another account.
 *
 * An account can grant PROXY over itself, or over an account for which it holds PROXY WITH
 * GRANT OPTION. A grant with that option also sets the grantee's global GRANT OPTION. Revoking
 * PROXY leaves that global option unchanged; REVOKE ALL leaves the PROXY grants unchanged.
 * In the ON position CURRENT_USER checks the current identity but stores or removes the
 * empty user and host mapping. Authority is checked after the proxied hostname and before
 * any grantee hostname is resolved. Observed through MySQL 5.6.51 through 9.1.0 SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/proxy-users.html.
 *
 * @visibility MySqlMemory
 */
final class Proxies
{
    /**
     * Grants a proxy after checking every named account.
     *
     * @throws SqlError When authority or a grantee is missing
     */
    public function grant(GrantProxy $statement, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $names->check([$statement->proxied, ...$names->users($statement->grantees)]);
        $proxied = $names->identity($statement->proxied, $session);
        $names->resolve($proxied, $session->diagnostics);
        $this->check($proxied, $session);
        $identities = [];
        foreach ($statement->grantees as $grantee) {
            $identity = $names->identity($grantee->user, $session);
            $names->resolve($identity, $session->diagnostics);
            $identities[] = $identity;
        }
        $proxied = $statement->proxied instanceof CurrentUser ? new Identity('', '') : $proxied;
        $found = [];
        $first = null;
        $legacy = (new Catalog($session->settings()->release()))->legacy();
        foreach ($identities as $index => $identity) {
            try {
                $found[] = $this->grantee($statement, $index, $identity, $session);
            } catch (SqlError $error) {
                if (!in_array($error->error, [AccountError::PasswordNoMatch, AccountError::CantCreateUserWithGrant], true)) {
                    throw $error;
                }
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $first ??= $error;
            }
        }
        foreach ($first !== null && !$legacy ? [] : $found as $account) {
            $names->ascii($account->identity, 3, $session->diagnostics);
            $account->grants->proxies[$proxied->key()] = [$proxied, $statement->withGrantOption];
            $account->grants->global->grantOption = $account->grants->global->grantOption || $statement->withGrantOption;
        }
        if ($first !== null) {
            throw new SqlError($first->error, $first->getMessage(), $first, recorded: true);
        }
    }

    /**
     * Resolves one grantee, preserving legacy authentication and account creation.
     *
     * @throws SqlError When the account cannot be created or its authentication is invalid
     */
    public function grantee(GrantProxy $statement, int $index, Identity $identity, Session $session): Account
    {
        if ((new Catalog($session->settings()->release()))->legacy()) {
            $single = new GrantProxy($statement->proxied, [$statement->grantees[$index]], $statement->withGrantOption);
            return (new GrantCommand())->accounts($single, [$identity], $session)[0];
        }
        return $session->instance->accounts->find($identity) ?? throw AccountError::CantCreateUserWithGrant->error();
    }

    /**
     * Revokes a proxy after checking authority, preserving the account's other grants.
     *
     * @throws SqlError When authority, a grantee or a grant is missing
     */
    public function revoke(RevokeProxy $statement, Session $session): void
    {
        $names = new Names($session->settings()->release());
        $names->check([$statement->proxied, ...$names->users($statement->users)]);
        $proxied = $names->identity($statement->proxied, $session);
        $names->resolve($proxied, $session->diagnostics);
        $this->check($proxied, $session);
        foreach ($statement->users as $user) {
            $names->resolve($names->identity($user->user, $session), $session->diagnostics);
        }
        $proxied = $statement->proxied instanceof CurrentUser ? new Identity('', '') : $proxied;
        $first = null;
        foreach ($names->users($statement->users) as $user) {
            try {
                $identity = $names->identity($user, $session);
                $accounts = $session->instance->accounts;
                if (!$statement->ignoreUnknownUser || $accounts->find($identity) !== null) {
                    $names->ascii($identity, 1, $session->diagnostics);
                }
                $unknown = !$session->settings()->legacy() && $accounts->stored($identity->stored()) !== null ? AccountError::PasswordNoMatch->error() : null;
                $found = (new RevokeCommand())->found([$user], $statement->ignoreUnknownUser, $session, false, $unknown);
                foreach ($found as $account) {
                    $names->ascii($account->identity, 1, $session->diagnostics);
                    $this->withdraw($account, $proxied, $statement->ifExists, $session);
                }
            } catch (SqlError $error) {
                $session->diagnostics->error($error->getCode(), $error->getMessage());
                $first ??= $error;
            }
        }
        if ($first !== null) {
            throw new SqlError($first->error, $first->getMessage(), $first, recorded: true);
        }
    }

    /**
     * Removes one account's proxy or reports its absent grant.
     *
     * @throws SqlError When the account has no such grant and IF EXISTS is absent
     */
    public function withdraw(Account $account, Identity $proxied, bool $ifExists, Session $session): void
    {
        if (!isset($account->grants->proxies[$proxied->key()])) {
            $error = AccountError::NonexistingGrant->error($account->identity->user, $account->identity->host);
            if (!$ifExists) {
                throw $error;
            }
            $session->diagnostics->warning($error->error, $error->getMessage());
        }
        unset($account->grants->proxies[$proxied->key()]);
    }

    /**
     * Checks authority over the proxied account, including the initial unrestricted root grant.
     *
     * @throws SqlError When the session may not administer this proxy
     */
    public function check(Identity $proxied, Session $session): void
    {
        $current = $session->variables->definer;
        if ($current->key() === $proxied->key() && $session->user === $current->user) {
            return;
        }
        foreach ($session->instance->accounts->find($current)?->grants->proxies ?? [] as [$identity, $option]) {
            if ($option && ($identity->user === '' || $identity->user === $proxied->user) && ($identity->host === '' || $identity->host === $proxied->host)) {
                return;
            }
        }
        [$user, $host] = explode('@', $session->variables->account, 2) + [1 => ''];

        throw AccountError::AccessDeniedNoPassword->error($user, $host);
    }
}

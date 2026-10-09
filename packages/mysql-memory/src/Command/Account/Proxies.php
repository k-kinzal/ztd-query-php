<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

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
 * empty user and host mapping. These transitions were verified on MySQL 5.6.51 and 8.4.7 through SQL.
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
        $identities = [];
        foreach ($statement->grantees as $grantee) {
            $identity = $names->identity($grantee->user, $session);
            $names->resolve($identity, $session->diagnostics);
            $identities[] = $identity;
        }
        $this->check($proxied, $session);
        $proxied = $statement->proxied instanceof CurrentUser ? new Identity('', '') : $proxied;
        $found = [];
        if ((new Catalog($session->settings()->release()))->legacy()) {
            $found = (new GrantCommand())->accounts($statement, $identities, $session);
        } else {
            foreach ($identities as $identity) {
                $found[] = $session->instance->accounts->find($identity) ?? throw AccountError::CantCreateUserWithGrant->error();
            }
        }
        foreach ($found as $account) {
            $account->grants->proxies[$proxied->key()] = [$proxied, $statement->withGrantOption];
            $account->grants->global->grantOption = $account->grants->global->grantOption || $statement->withGrantOption;
        }
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
        foreach ($statement->users as $user) {
            $names->resolve($names->identity($user->user, $session), $session->diagnostics);
        }
        $this->check($proxied, $session);
        $proxied = $statement->proxied instanceof CurrentUser ? new Identity('', '') : $proxied;
        $found = (new RevokeCommand())->found($names->users($statement->users), $statement->ignoreUnknownUser, $session, false);
        foreach ($found as $account) {
            if (!isset($account->grants->proxies[$proxied->key()])) {
                $error = AccountError::NonexistingGrant->error($account->identity->user, $account->identity->host);
                if (!$statement->ifExists) {
                    throw $error;
                }
                $session->diagnostics->warning($error->error, $error->getMessage());
            }
            unset($account->grants->proxies[$proxied->key()]);
        }
    }

    /**
     * Checks authority over the proxied account, including the initial unrestricted root grant.
     *
     * @throws SqlError When the session may not administer this proxy
     */
    public function check(Identity $proxied, Session $session): void
    {
        [$user, $host] = explode('@', $session->variables->definer, 2) + [1 => ''];
        $current = new Identity($user, $host);
        if ($current->key() === $proxied->key() && $session->user === $user) {
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

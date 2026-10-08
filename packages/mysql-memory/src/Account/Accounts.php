<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

/**
 * The accounts and roles of a server, the roles granted to each, and the default roles of each.
 *
 * A server starts with the accounts of a new installation: root at `localhost` and at `%` with
 * every privilege and GRANT OPTION, root at `localhost` also with PROXY on ''@'', and the locked
 * system accounts mysql.infoschema, mysql.session and mysql.sys with their privileges (verified
 * on a live 8.4 server). Roles are accounts; granting one to an account makes it a role of it,
 * with or without ADMIN OPTION.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/default-privileges.html,
 * https://dev.mysql.com/doc/refman/8.4/en/roles.html.
 *
 * @visibility MySqlMemory
 */
final class Accounts
{
    /**
     * @param array<string, Account> $accounts The accounts, by key
     * @param array<string, array<string, array{Identity, bool}>> $edges The roles granted to each account, each telling whether ADMIN OPTION is held, by the keys of the account and the role
     * @param array<string, array<string, Identity>> $defaults The default roles of each account, by the keys of the account and the role
     */
    public function __construct(public array $accounts = [], public array $edges = [], public array $defaults = [])
    {
    }

    /**
     * Answers the accounts of a new installation.
     */
    public static function installed(): self
    {
        $accounts = new self();
        foreach (['localhost', '%'] as $host) {
            $root = new Account(new Identity('root', $host));
            $root->grants->global->add(Catalog::STATIC);
            $root->grants->global->grantOption = true;
            $root->grants->dynamic = array_fill_keys(Catalog::DYNAMIC, true);
            if ($host === 'localhost') {
                $root->grants->proxies[(new Identity('', ''))->key()] = [new Identity('', ''), true];
            }
            $accounts->add($root);
        }
        $locked = '$A$005$THISISACOMBINATIONOFINVALIDSALTANDPASSWORDTHATMUSTNEVERBRBEUSED';
        $schema = new Account(new Identity('mysql.infoschema', 'localhost'), hash: $locked, password: null, locked: true);
        $schema->grants->global->add(['SELECT']);
        $schema->grants->dynamic = ['AUDIT_ABORT_EXEMPT' => false, 'FIREWALL_EXEMPT' => false, 'SYSTEM_USER' => false];
        $accounts->add($schema);
        $session = new Account(new Identity('mysql.session', 'localhost'), hash: $locked, password: null, locked: true);
        $session->grants->global->add(['SHUTDOWN', 'SUPER']);
        $session->grants->dynamic = array_fill_keys(['AUDIT_ABORT_EXEMPT', 'AUTHENTICATION_POLICY_ADMIN', 'BACKUP_ADMIN', 'CLONE_ADMIN', 'CONNECTION_ADMIN', 'FIREWALL_EXEMPT', 'PERSIST_RO_VARIABLES_ADMIN', 'SESSION_VARIABLES_ADMIN', 'SYSTEM_USER', 'SYSTEM_VARIABLES_ADMIN'], false);
        $session->grants->database('performance_schema')->add(['SELECT']);
        $session->grants->table('mysql', 'user')->add(['SELECT']);
        $accounts->add($session);
        $sys = new Account(new Identity('mysql.sys', 'localhost'), hash: $locked, password: null, locked: true);
        $sys->grants->dynamic = ['AUDIT_ABORT_EXEMPT' => false, 'FIREWALL_EXEMPT' => false, 'SYSTEM_USER' => false];
        $sys->grants->database('sys')->add(['TRIGGER']);
        $sys->grants->table('sys', 'sys_config')->add(['SELECT']);
        $accounts->add($sys);

        return $accounts;
    }

    /**
     * Finds an account, or answers null.
     */
    public function find(Identity $identity): ?Account
    {
        return $this->accounts[$identity->key()] ?? null;
    }

    /**
     * Adds an account.
     */
    public function add(Account $account): void
    {
        $this->accounts[$account->identity->key()] = $account;
    }

    /**
     * Drops an account, the roles granted to it, its grants as a role, and its place among default roles.
     */
    public function drop(Identity $identity): void
    {
        $key = $identity->key();
        unset($this->accounts[$key], $this->edges[$key], $this->defaults[$key]);
        foreach ($this->edges as $grantee => $roles) {
            unset($roles[$key]);
            $this->edges[$grantee] = $roles;
        }
        foreach ($this->defaults as $grantee => $roles) {
            unset($roles[$key]);
            $this->defaults[$grantee] = $roles;
        }
        $this->edges = array_filter($this->edges, static fn (array $roles): bool => $roles !== []);
        $this->defaults = array_filter($this->defaults, static fn (array $roles): bool => $roles !== []);
    }

    /**
     * Renames an account, keeping its privileges, roles and default roles.
     */
    public function rename(Identity $from, Identity $to): void
    {
        $account = $this->find($from);
        if ($account === null) {
            return;
        }
        $old = $from->key();
        $new = $to->key();
        unset($this->accounts[$old]);
        $account->identity = $to;
        $this->accounts[$new] = $account;
        if (isset($this->edges[$old])) {
            $this->edges[$new] = $this->edges[$old];
            unset($this->edges[$old]);
        }
        if (isset($this->defaults[$old])) {
            $this->defaults[$new] = $this->defaults[$old];
            unset($this->defaults[$old]);
        }
    }

    /**
     * Grants a role to an account; ADMIN OPTION once held is kept.
     */
    public function grant(Identity $role, Identity $grantee, bool $admin): void
    {
        $held = $this->edges[$grantee->key()][$role->key()][1] ?? false;
        $this->edges[$grantee->key()][$role->key()] = [$role, $held || $admin];
    }

    /**
     * Revokes a role from an account; it is no longer a default role of the account either.
     */
    public function revoke(Identity $role, Identity $grantee): void
    {
        unset($this->edges[$grantee->key()][$role->key()], $this->defaults[$grantee->key()][$role->key()]);
        $this->edges = array_filter($this->edges, static fn (array $roles): bool => $roles !== []);
        $this->defaults = array_filter($this->defaults, static fn (array $roles): bool => $roles !== []);
    }

    /**
     * Answers the roles granted to an account, each telling whether ADMIN OPTION is held, by key.
     *
     * @return array<string, array{Identity, bool}>
     */
    public function roles(Identity $grantee): array
    {
        return $this->edges[$grantee->key()] ?? [];
    }

    /**
     * Tells whether an account is granted to some account as a role.
     */
    public function granted(Identity $role): bool
    {
        foreach ($this->edges as $roles) {
            if (isset($roles[$role->key()])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a role is granted to an account directly or through other roles.
     */
    public function reaches(Identity $grantee, Identity $role): bool
    {
        $seen = [];
        $pending = [$grantee->key()];
        while ($pending !== []) {
            $key = array_pop($pending);
            if ($key === $role->key()) {
                return true;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            foreach (array_keys($this->edges[$key] ?? []) as $next) {
                $pending[] = $next;
            }
        }

        return false;
    }

    /**
     * Revokes the privileges every account holds on a routine, as DROP PROCEDURE and DROP FUNCTION do (automatic_sp_privileges).
     *
     * @param string $kind PROCEDURE or FUNCTION
     */
    public function forget(string $kind, string $database, string $name): void
    {
        foreach ($this->accounts as $account) {
            unset($account->grants->routines[$kind . "\0" . $database . "\0" . mb_strtolower($name, 'UTF-8')]);
        }
    }

    /**
     * Answers a copy that changes apart from this one, to restore after a statement that fails.
     */
    public function copy(): self
    {
        return new self(array_map(static fn (Account $account): Account => $account->copy(), $this->accounts), $this->edges, $this->defaults);
    }

    /**
     * Takes the accounts, roles and default roles of a copy back.
     */
    public function restore(self $copy): void
    {
        $this->accounts = $copy->accounts;
        $this->edges = $copy->edges;
        $this->defaults = $copy->defaults;
    }
}

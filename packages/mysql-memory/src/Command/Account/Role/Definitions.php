<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Role;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use WeakMap;

/**
 * Creates role accounts, retaining ordered conditional skips and invocation state.
 *
 * @visibility MySqlMemory
 */
final class Definitions
{
    /**
     * Interprets a complete account request, including intrinsic contradictions.
     */
    public function execute(CreateRole $statement, Session $session, Context $context): Reply
    {
        $names = new Names($session->settings()->release());
        $names->check($statement->roles);
        $roles = array_map(static fn ($role): Identity => $names->identity($role, $session), $statement->roles);
        return $this->create($roles, $statement->ifNotExists, $session, $context, $statement->problem === null ? $statement : null);
    }

    /**
     * Applies one complete definition atomically, preserving preceding notices.
     *
     * @param list<Identity> $roles The resolved accounts in written order
     * @param CreateRole|null $instruction The retained stored instruction when available
     * @throws \MySqlMemory\Error\SqlError When an account cannot be created
     */
    public function create(array $roles, bool $ifNotExists, Session $session, Context $context, ?CreateRole $instruction = null): Reply
    {
        $created = $this->resolve($roles, $ifNotExists, $session, $context, $instruction);
        $accounts = $session->instance->accounts;
        foreach ($created as [$role, $index]) {
            $accounts->add(Account::role($role));
            if ($instruction !== null) {
                $this->plugin($instruction, $index, $role, $session, true);
            }
            (new Names())->ascii($role, 8, $context->diagnostics);
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Resolves the complete account batch before publishing any newly created role.
     *
     * @param list<Identity> $roles The resolved accounts in order
     * @return array<string, array{Identity, int}> New roles and their instruction positions
     * @throws \MySqlMemory\Error\SqlError When the account batch is refused
     */
    public function resolve(array $roles, bool $ifNotExists, Session $session, Context $context, ?CreateRole $instruction): array
    {
        $accounts = $session->instance->accounts;
        $failed = [];
        $created = [];
        $failure = null;
        foreach ($roles as $index => $role) {
            if ($role->user === '' && $ifNotExists && $accounts->find($role) !== null) {
                (new Names())->ascii($role, 1, $context->diagnostics);
                $context->diagnostics->note(AccountError::UserAlreadyExists, AccountError::UserAlreadyExists->message($role->quoted()));
                continue;
            }
            if ($role->user === '') {
                $accounts->reload();
                throw (new Names($session->settings()->release()))->failure('CREATE ROLE', 'anonymous user');
            }
            try {
                if ($instruction !== null) {
                    $this->plugin($instruction, $index, $role, $session);
                }
            } catch (\MySqlMemory\Error\SqlError $error) {
                $failure ??= $error;
                $context->diagnostics->error($error->getCode(), $error->getMessage());
                continue;
            }
            (new Names())->ascii($role, 1, $context->diagnostics);
            if ($accounts->find($role) !== null || $accounts->stored($role->stored()) !== null || isset($created[$role->stored()->key()])) {
                $failed[] = $role;
                if ($ifNotExists) {
                    $context->diagnostics->note(AccountError::UserAlreadyExists, AccountError::UserAlreadyExists->message($role->quoted()));
                }
                continue;
            }
            $created[$role->stored()->key()] = [$role, $index];
        }
        if ($failure !== null) {
            $accounts->reload();
            throw new \MySqlMemory\Error\SqlError($failure->error, $failure->getMessage(), recorded: true);
        }
        if ($failed !== [] && !$ifNotExists) {
            $accounts->reload();
            throw (new Names($session->settings()->release()))->failure('CREATE ROLE', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $failed)));
        }
        return $created;
    }

    /**
     * Retains whether a stored instruction initialized a new role's authentication.
     * On MySQL 8.4+, skipping an existing role on first use leaves an empty plugin
     * for a later creation by that same instruction. Observed through CALL/drop/CALL.
     *
     * @throws \MySqlMemory\Error\SqlError When the retained authentication plugin is unavailable
     */
    public function plugin(CreateRole $instruction, int $index, Identity $role, Session $session, bool $initialized = false): void
    {
        if ($session->program === null || version_compare($session->instance->version, '8.4.0', '<')) {
            return;
        }
        $instruction = $session->program->instruction instanceof CreateRole ? $session->program->instruction : $instruction;
        $cache = $session->preparation->roleCreations ??= new WeakMap();
        $states = $cache[$instruction] ?? [];
        $missing = $session->instance->accounts->find($role) === null;
        if ($initialized) {
            $states[$index] = true;
        } elseif (!$missing) {
            $states[$index] ??= false;
        }
        $cache[$instruction] = $states;
        if ($missing && ($states[$index] ?? true) === false) {
            throw \MySqlMemory\Error\Family\AdministrationError::PluginIsNotLoaded->error('');
        }
    }
}

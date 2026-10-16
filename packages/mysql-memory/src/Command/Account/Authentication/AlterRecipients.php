<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account\Authentication;

use MySqlMemory\Account\Credentials;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Account\AlterUserCommand;
use MySqlMemory\Command\Account\Names;
use MySqlMemory\Command\Account\Options;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Access\PasswordAccess;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

/**
 * Applies account occurrences in order while retaining every failed recipient's condition.
 * Modern releases roll back account data on failure. A session that successfully addressed
 * its own account in that failed batch must reset its password, independently of rollback.
 * MySQL 5.7 retains successful changes and appends its aggregate account error.
 * Verified through ALTER USER, SHOW WARNINGS and subsequent queries on isolated servers.
 *
 * @visibility MySqlMemory
 */
final class AlterRecipients
{
    /** @var list<Identity> The recipients missing after authentication checks */
    private array $missing = [];

    /** @var list<array{Identity, string}> The generated credentials in occurrence order */
    private array $generated = [];

    private bool $currentChanged = false;

    public function __construct(private readonly AlterUserCommand $command, private readonly AlterUser $statement, private readonly Session $session, private readonly Options $options)
    {
    }

    /**
     * Changes each recipient and returns the generated passwords only on success.
     *
     * @return list<array{Identity, string}>
     * @throws SqlError When any recipient is refused
     */
    public function apply(): array
    {
        $accounts = $this->session->instance->accounts;
        $saved = $accounts->copy();
        $first = null;
        foreach ($this->statement->users as $user) {
            try {
                $this->recipient($user);
            } catch (SqlError $error) {
                $this->session->diagnostics->error($error->getCode(), $error->getMessage());
                $first ??= $error;
            }
        }
        $legacy = $this->session->settings()->release() === GrammarRelease::MySql5744;
        if (($first === null || $legacy) && (!$this->statement->ifExists && $this->missing !== [] || $legacy && $first !== null)) {
            $error = (new Names($this->session->settings()->release()))->failure('ALTER USER', implode(',', array_map(static fn (Identity $identity): string => $identity->quoted(), $this->missing)));
            $this->session->diagnostics->error($error->getCode(), $error->getMessage());
            $first ??= $error;
        }
        if ($first !== null) {
            if (!$legacy) {
                $accounts->restore($saved);
                $this->session->passwordExpired = $this->session->passwordExpired || $this->currentChanged;
            }
            throw new SqlError($first->error, $first->getMessage(), $first, recorded: true);
        }

        return $this->generated;
    }

    /**
     * Checks and changes one occurrence against the state left by earlier occurrences.
     *
     * @throws SqlError When its authentication or options are refused
     */
    private function recipient(UserAlteration $user): void
    {
        $names = new Names($this->session->settings()->release());
        $identity = $names->identity($names->users([$user])[0], $this->session);
        $account = $this->session->instance->accounts->find($identity);
        if ($user instanceof UserSpecification) {
            $this->options->check($user->identification, $account->plugin ?? (new Credentials($this->session->settings()->release()))->default());
        }
        if ($user instanceof FactorChange) {
            $this->command->factors($user, $account, $this->session);
        }
        if ($account === null) {
            $this->missing[] = $identity;
            if ($this->statement->ifExists) {
                $this->session->diagnostics->note(AccountError::UserDoesNotExist, $names->existence($identity, false));
            }
            return;
        }
        $this->generated = [...$this->generated, ...$this->command->change([[$account, $user]], $this->statement, $this->session, $this->options)];
        $this->currentChanged = $this->currentChanged || $identity->key() === $this->session->variables->definer->key();
        (new PasswordAccess())->changed($account, $this->session);
    }
}

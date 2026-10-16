<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Password;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Target\Password\Retention;
use Fuzz\Target\RandomPasswords;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

/**
 * Validates a generated memory password by subsequent constructed account requests.
 * Retention checks use this instance's own hashes, never a native hash or password.
 */
final class MemoryState
{
    /**
     * Samples the current identity, generation length and previous primary hash.
     */
    public function capture(Session $session, bool $retain): ?RandomPasswords
    {
        $account = $session->instance->accounts->find($session->variables->definer);
        if ($account === null || ($retain && $account->hash === '')) {
            return null;
        }

        return new RandomPasswords($session->variables->count('generated_random_password_length', 20), $account->identity->text(), $retain ? new Retention($account->hash) : null);
    }

    /**
     * Checks replacement behavior and, when requested, preservation and discard of the secondary.
     */
    public function verifies(Session $session, RandomPasswords $policy, string $password): bool
    {
        if ($policy->retention !== null && !$this->retained($session, $policy->retention->primary)) {
            return false;
        }
        $observer = new Memory();
        $wrong = $observer->observe($session, new SetPassword(null, new Password(new Text($password)), new Text($password . '!')));
        if (($wrong['error'] ?? null) !== [3891, 'HY000', 'Incorrect current password. Specify the correct password which has to be replaced.']) {
            return false;
        }
        $right = $observer->observe($session, new SetPassword(null, new Password(new Text($password)), new Text($password)));
        if (!$this->completed($right)) {
            return false;
        }

        return $policy->retention === null || $this->discarded($session, $policy->retention->primary);
    }

    /**
     * Verifies that the previous primary remains the stored secondary credential.
     */
    public function retained(Session $session, string $primary): bool
    {
        $account = $session->instance->accounts->find($session->variables->definer);

        return $primary !== '' && $account !== null && $account->hash !== '' && ($account->secondary[0] ?? null) === $primary;
    }

    /**
     * Requires DISCARD to remove only the old password after ordinary replacement preserved it.
     */
    public function discarded(Session $session, string $primary): bool
    {
        if (!$this->retained($session, $primary)) {
            return false;
        }
        $account = $session->instance->accounts->find($session->variables->definer);
        $before = $account?->hash;
        $result = (new Memory())->observe($session, new AlterUser(false, [new UserSpecification(new CurrentUser(), discardOld: true)]));
        $after = $session->instance->accounts->find($session->variables->definer);

        return $this->completed($result) && $before !== null && $after !== null && $after->hash === $before && $after->secondary === null;
    }

    /**
     * Checks the complete expected response, including absence of extra fields or errors.
     *
     * @param array<string, mixed> $observation The direct response to a validation request
     */
    public function completed(array $observation): bool
    {
        return array_keys($observation) === ['results', 'warnings', 'disconnected']
            && ($observation['results'] ?? null) === [['affected' => 0, 'lastInsertId' => '0']]
            && ($observation['warnings'] ?? null) === []
            && ($observation['disconnected'] ?? null) === false;
    }

}

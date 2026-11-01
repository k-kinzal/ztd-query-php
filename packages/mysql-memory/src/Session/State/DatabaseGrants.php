<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Privileges;

/**
 * The database privilege value reused by an empty REVOKE in MySQL 8.4 and 9.1.
 *
 * Public SQL observations on fresh 8.4.7 and 9.1.0 servers show that REVOKE USAGE,
 * or IF EXISTS with only inapplicable dynamic privileges, can copy the last database
 * grant value to an account without a grant at that level. A failed database grant
 * operation leaves TRIGGER as that value. Reconnection and FLUSH TABLES clear it.
 * This state is independent of committed grants and survives statement rollback.
 * No server implementation is used to derive these transitions.
 *
 * @visibility MySqlMemory
 */
final class DatabaseGrants
{
    /**
     * @param Privileges $last The database privilege value retained by this connection
     */
    public function __construct(public Privileges $last = new Privileges())
    {
    }

    /**
     * Retains the value left by a refused database-level account operation.
     *
     * @param array{string, string, string} $target
     */
    public function refused(array $target): void
    {
        if ($target[0] === 'DATABASE') {
            $this->last = new Privileges(['TRIGGER' => true]);
        }
    }

    /**
     * Retains a copy of the account's database privileges after an operation.
     *
     * @param array{string, string, string} $target
     */
    public function remember(Account $account, array $target): void
    {
        if ($target[0] === 'DATABASE') {
            $this->last = clone ($account->grants->databases[$target[1]] ?? new Privileges());
        }
    }

    /**
     * Preserves an existing database grant or reuses the preceding value for an absent one.
     */
    public function reuse(Account $account, string $database): void
    {
        $privileges = $account->grants->databases[$database] ?? clone $this->last;
        if (!$privileges->empty()) {
            $account->grants->databases[$database] = $privileges;
        }
        $this->last = clone $privileges;
    }

    /**
     * Forgets the cached privilege value when the grant table is flushed.
     */
    public function clear(): void
    {
        $this->last = new Privileges();
    }
}

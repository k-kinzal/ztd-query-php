<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One numeric account policy in the range MySQL accepts for its kind.
 * @visibility public
 * @example Reading a password expiry interval
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER u PASSWORD EXPIRE INTERVAL 30 DAY');
 *     [$statement->policies[0]->kind->value, $statement->policies[0]->value] // => ['PASSWORD EXPIRE INTERVAL', 30]
 * @example Rejecting a zero-day expiry interval
 *     new \SqlSemantics\Model\Definition\Account\Policy\AccountLimit(\SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind::PasswordExpiryDays, 0); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AccountLimit
{
    /**
     * Expiry intervals require 1 to 65535 days; login attempts and lock days require 0 to 32767; history and reuse are nonnegative.
     * @throws InvalidStructure
     */
    public function __construct(public readonly AccountLimitKind $kind, public readonly int $value)
    {
        [$minimum, $maximum] = match ($kind) {
            AccountLimitKind::PasswordExpiryDays => [1, 65535],
            AccountLimitKind::FailedLoginAttempts, AccountLimitKind::PasswordLockDays => [0, 32767],
            AccountLimitKind::PasswordHistory, AccountLimitKind::PasswordReuseDays => [0, PHP_INT_MAX],
        };
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidStructure('The account policy value is outside the range MySQL accepts for ' . $kind->value . '.');
        }
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

/**
 * Numeric account policies; day-based kinds are written with a trailing DAY unit.
 * @visibility public
 * @example Inspecting a numeric policy kind
 *     \SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind::PasswordExpiryDays->value // => 'PASSWORD EXPIRE INTERVAL'
 */
enum AccountLimitKind: string
{
    case PasswordExpiryDays = 'PASSWORD EXPIRE INTERVAL';
    case PasswordHistory = 'PASSWORD HISTORY';
    case PasswordReuseDays = 'PASSWORD REUSE INTERVAL';
    case FailedLoginAttempts = 'FAILED_LOGIN_ATTEMPTS';
    case PasswordLockDays = 'PASSWORD_LOCK_TIME';
}

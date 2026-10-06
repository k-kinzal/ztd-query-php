<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

/**
 * The password management and account locking options of CREATE USER and ALTER USER.
 *
 * Mirrors the fields of the server's LEX_ALTER (alter_password): account
 * locking, password expiry, password history, password reuse interval,
 * current-password verification, and failed-login tracking. A case that
 * takes a number is written with it: `PASSWORD EXPIRE INTERVAL n DAY`,
 * `PASSWORD HISTORY n`, `PASSWORD REUSE INTERVAL n DAY`,
 * `FAILED_LOGIN_ATTEMPTS n`, `PASSWORD_LOCK_TIME n`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-password-management,
 * https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-account-locking.
 *
 * @visibility public
 * @example Reading the words of an option
 *     \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::ReuseInterval->words() // => ['PASSWORD', 'REUSE', 'INTERVAL']
 */
enum AccountOptionKind
{
    case AccountUnlock;
    case AccountLock;
    case ExpireNow;
    case ExpireInterval;
    case ExpireNever;
    case ExpireDefault;
    case HistoryCount;
    case HistoryDefault;
    case ReuseInterval;
    case ReuseDefault;
    case RequireCurrent;
    case RequireCurrentDefault;
    case RequireCurrentOptional;
    case FailedLoginAttempts;
    case LockTime;
    case LockTimeUnbounded;

    /**
     * Answers the keywords written before the number, or the whole option when it takes none.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return match ($this) {
            self::AccountUnlock => ['ACCOUNT', 'UNLOCK'],
            self::AccountLock => ['ACCOUNT', 'LOCK'],
            self::ExpireNow => ['PASSWORD', 'EXPIRE'],
            self::ExpireInterval => ['PASSWORD', 'EXPIRE', 'INTERVAL'],
            self::ExpireNever => ['PASSWORD', 'EXPIRE', 'NEVER'],
            self::ExpireDefault => ['PASSWORD', 'EXPIRE', 'DEFAULT'],
            self::HistoryCount => ['PASSWORD', 'HISTORY'],
            self::HistoryDefault => ['PASSWORD', 'HISTORY', 'DEFAULT'],
            self::ReuseInterval => ['PASSWORD', 'REUSE', 'INTERVAL'],
            self::ReuseDefault => ['PASSWORD', 'REUSE', 'INTERVAL', 'DEFAULT'],
            self::RequireCurrent => ['PASSWORD', 'REQUIRE', 'CURRENT'],
            self::RequireCurrentDefault => ['PASSWORD', 'REQUIRE', 'CURRENT', 'DEFAULT'],
            self::RequireCurrentOptional => ['PASSWORD', 'REQUIRE', 'CURRENT', 'OPTIONAL'],
            self::FailedLoginAttempts => ['FAILED_LOGIN_ATTEMPTS'],
            self::LockTime => ['PASSWORD_LOCK_TIME'],
            self::LockTimeUnbounded => ['PASSWORD_LOCK_TIME', 'UNBOUNDED'],
        };
    }

    /**
     * Tells whether the option is written with a number.
     */
    public function numbered(): bool
    {
        return in_array($this, [self::ExpireInterval, self::HistoryCount, self::ReuseInterval, self::FailedLoginAttempts, self::LockTime], true);
    }

    /**
     * Answers the keyword written after the number: DAY for the two intervals.
     */
    public function unit(): ?string
    {
        return $this === self::ExpireInterval || $this === self::ReuseInterval ? 'DAY' : null;
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

/**
 * Keyword-only account lock and password policies; when repeated, the last request wins as in MySQL.
 * @visibility public
 * @example Inspecting a policy
 *     \SqlSemantics\Model\Definition\Account\Policy\AccountPolicy::Lock->value // => 'ACCOUNT LOCK'
 */
enum AccountPolicy: string
{
    case Lock = 'ACCOUNT LOCK';
    case Unlock = 'ACCOUNT UNLOCK';
    case ExpirePassword = 'PASSWORD EXPIRE';
    case NeverExpirePassword = 'PASSWORD EXPIRE NEVER';
    case DefaultPasswordExpiry = 'PASSWORD EXPIRE DEFAULT';
    case DefaultPasswordHistory = 'PASSWORD HISTORY DEFAULT';
    case DefaultPasswordReuse = 'PASSWORD REUSE INTERVAL DEFAULT';
    case RequireCurrentPassword = 'PASSWORD REQUIRE CURRENT';
    case DefaultCurrentPasswordRequirement = 'PASSWORD REQUIRE CURRENT DEFAULT';
    case OptionalCurrentPassword = 'PASSWORD REQUIRE CURRENT OPTIONAL';
    case UnboundedPasswordLock = 'PASSWORD_LOCK_TIME UNBOUNDED';
}

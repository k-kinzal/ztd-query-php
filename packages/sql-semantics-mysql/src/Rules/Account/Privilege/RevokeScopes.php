<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account\Privilege;

use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;

/**
 * Distinguishes routine targets from wildcard privilege levels.
 * Modern REVOKE ALL on the global level ignores the written routine kind.
 * Legacy SQL retains that form only as an instruction that refuses invocation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RevokeScopes
{
    /**
     * Identifies a complete request whose target belongs to a stored instruction.
     *
     * @param list<Privilege> $privileges The revoked privileges
     */
    public function deferred(array $privileges, ObjectKind $kind, PrivilegeLevel $level, bool $legacy = false): bool
    {
        return $kind !== ObjectKind::Table && !$level instanceof ObjectLevel && ($legacy || !$this->globalAll($privileges, $kind, $level));
    }

    /**
     * Identifies the global ALL interpretation admitted by modern releases.
     *
     * @param list<Privilege> $privileges The revoked privileges
     */
    public function globalAll(array $privileges, ObjectKind $kind, PrivilegeLevel $level): bool
    {
        return $kind !== ObjectKind::Table && $level instanceof GlobalLevel && count($privileges) === 1 && $privileges[0] instanceof AllPrivileges;
    }
}

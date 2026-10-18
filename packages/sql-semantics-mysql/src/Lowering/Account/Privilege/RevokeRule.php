<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account\Privilege;

use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Account\Privilege\RevokeScopes;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;

/**
 * Publishes the release's revocation role after all operands have been read.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RevokeRule
{
    /**
     * @param Lowering $lowering The selected source language
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Preserves the complete request when ordinary statement construction excludes it.
     *
     * @param list<Privilege> $privileges The ordered privileges
     * @param list<UserSpecification> $users The ordered recipients
     * @throws RejectedInput When the request belongs to a stored instruction
     */
    public function statement(bool $ifExists, array $privileges, ObjectKind $kind, PrivilegeLevel $level, array $users, bool $ignoreUnknownUser = false, int $end = 0): RevokePrivileges
    {
        $legacy = in_array($this->lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        if ((new RevokeScopes())->deferred($privileges, $kind, $level, $legacy)) {
            $request = new DeferredRevokePrivileges($ifExists, $privileges, $kind, $level, $users, $ignoreUnknownUser);
            throw new RejectedInput($request->problem(), $end, false);
        }
        return new RevokePrivileges($ifExists, $privileges, $kind, $level, $users, $ignoreUnknownUser);
    }
}

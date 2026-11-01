<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account\Privilege;

use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

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
     * Publishes the complete revocation request; a standalone request whose privileges do not fit its level is refused.
     *
     * @param list<Privilege> $privileges The ordered privileges
     * @param list<UserSpecification> $users The ordered recipients
     * @throws \SqlSemantics\Construction\RejectedInput When a standalone request names a privilege outside its level
     */
    public function statement(bool $ifExists, array $privileges, ObjectKind $kind, PrivilegeLevel $level, array $users, bool $ignoreUnknownUser = false, int $end = 0, bool $tableKeyword = false): RevokePrivileges
    {
        return (new LevelRule($this->lowering))->checked(new RevokePrivileges($ifExists, $privileges, $kind, $level, $users, $ignoreUnknownUser, tableKeyword: $tableKeyword), $end);
    }
}

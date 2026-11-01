<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account\Privilege;

use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;

/**
 * Refuses a standalone privilege request whose privileges do not fit its level.
 *
 * The server checks the level once the statement is read (ER_ILLEGAL_GRANT_FOR_TABLE,
 * ER_ILLEGAL_PRIVILEGE_LEVEL, ER_WRONG_USAGE, ER_UNSUPPORTED_USE_OF_GRANT_AS; verified on
 * 5.7.44 and 8.4.7). A stored program body keeps the request and the server reports the
 * condition when the program runs, so a body member is not refused.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LevelRule
{
    /**
     * @param Lowering $lowering The selected source language
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Answers the request when its level fits, or when a stored program body keeps it.
     *
     * @template T of GrantPrivileges|RevokePrivileges
     * @param T $statement
     * @return T
     * @throws RejectedInput When a standalone request names a privilege outside its level
     */
    public function checked(GrantPrivileges|RevokePrivileges $statement, int $end): GrantPrivileges|RevokePrivileges
    {
        $problem = $this->lowering->stored() ? null : ($statement instanceof RevokePrivileges ? $statement->problem($this->lowering->profile->grammar) : $statement->problem());
        if ($problem !== null) {
            throw new RejectedInput($problem, $end, false);
        }

        return $statement;
    }
}

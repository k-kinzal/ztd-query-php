<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * The role option VALID UNTIL: the time after which the password of the role is no longer valid.
 *
 * The time is written as a string that the server reads as a timestamp with
 * the settings of the session when the statement runs.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility SqlSemantics
 */
final class RoleValidity implements RoleOption
{
    use Snapshot;

    /**
     * @param StringConstant $until The time as written
     */
    public function __construct(public readonly StringConstant $until)
    {
    }

    /**
     * Answers the option the time fills.
     */
    public function option(): string
    {
        return 'validUntil';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALID', 'UNTIL')->node($this->until);
    }
}

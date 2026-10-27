<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;

/**
 * The role option VALID UNTIL: the time after which the password of the role is no longer valid.
 *
 * The time is written as a string that the server reads as a timestamp with
 * the settings of the session when the statement runs.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the time
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE ROLE joe VALID UNTIL 'infinity'");
 *     $operation->options[0]->until->value // => 'infinity'
 */
final class RoleValidity implements RoleOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param StringConstant $until The time as written
     */
    public function __construct(public readonly StringConstant $until, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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

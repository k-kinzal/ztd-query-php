<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem\Type;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A resolved operand domain outside a native function's accepted argument types.
 *
 * This condition is independent of the operand's runtime value, including NULL.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_incorrect_type.
 *
 * @visibility public
 * @example Reporting an incompatible reference identifier domain
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch(new \SqlSemantics\Statement\Identifier\Name('st_pointfromgeohash'), 'SRID'))->message() // => "Incorrect type for argument SRID in function st_pointfromgeohash."
 */
final class ArgumentDomainMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $function The function's diagnostic name
     * @param string $argument The function's named argument role
     */
    public function __construct(public readonly Name $function, public readonly string $argument)
    {
    }

    /**
     * Describes the rejected argument domain without retaining its source expression.
     */
    public function message(): string
    {
        return 'Incorrect type for argument ' . $this->argument . ' in function ' . $this->function->value . '.';
    }
}

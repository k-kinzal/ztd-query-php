<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural\PostgreSql;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One argument of a PostgreSQL procedure call: its value, the parameter it names, if any, and whether it supplies a variadic array.
 * @visibility public
 * @example Reading positional and named arguments
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CALL refresh(1, since => 2)');
 *     [$statement->arguments[0]->name, $statement->arguments[1]->name] // => [null, 'since']
 */
final class ProcedureArgument
{
    /**
     * @param Expression $value Argument expression, not evaluated by binding
     * @param string|null $name Parameter name for named notation; null for positional notation
     * @param bool $variadic Whether VARIADIC passes the value as the whole variadic array
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $value, public readonly ?string $name = null, public readonly bool $variadic = false)
    {
        if ($name === '') {
            throw new InvalidStructure('A named procedure argument requires a nonempty parameter name.');
        }
    }
}

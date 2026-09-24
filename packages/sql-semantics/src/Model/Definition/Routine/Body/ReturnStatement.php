<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * RETURN expression: ends a stored function with its result.
 * @visibility public
 * @example Reading the returned expression
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a * 2');
 *     $statement->body->value->kind === \SqlSemantics\Model\ExpressionKind::Operator // => true
 */
final class ReturnStatement implements ProgramStatement
{
    /**
     * Requires a MySQL expression.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $value)
    {
        if ($value->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('RETURN requires a MySQL expression.');
        }
    }
}

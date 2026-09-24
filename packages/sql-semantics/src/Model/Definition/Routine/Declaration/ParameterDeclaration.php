<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\RoutineParameter;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A declared routine parameter with its optional default value; only input parameters take defaults and no parameter is a set.
 * @visibility public
 * @example Reading a parameter default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f(n integer DEFAULT 1) RETURNS integer LANGUAGE sql AS 'SELECT n'");
 *     $statement->parameters[0]->parameter->name // => 'n'
 *     $statement->parameters[0]->default->text // => '1'
 * @example Rejecting a default for an output parameter
 *     $out = new \SqlSemantics\Model\Definition\Routine\RoutineParameter(\SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::PostgreSql, 'integer'), \SqlSemantics\Model\Definition\Routine\ParameterMode::Output, 'n');
 *     new \SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration($out, \SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::PostgreSql)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ParameterDeclaration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly RoutineParameter $parameter, public readonly ?Expression $default = null)
    {
        if ($parameter->setOf) {
            throw new InvalidStructure('A routine parameter cannot be a set.');
        }
        if ($default !== null && ($parameter->mode === ParameterMode::Output || $default->type->dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('Only input parameters take a PostgreSQL default value.');
        }
    }

    /**
     * Whether the argument is passed by the caller.
     */
    public function input(): bool
    {
        return $this->parameter->mode !== ParameterMode::Output;
    }

    /**
     * Whether the argument is returned to the caller.
     */
    public function output(): bool
    {
        return in_array($this->parameter->mode, [ParameterMode::Output, ParameterMode::InputOutput], true);
    }
}

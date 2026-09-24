<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A named stored procedure parameter with its direction; an omitted direction is IN.
 * @visibility public
 * @example Reading a parameter direction
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT, INOUT b INT) BEGIN END');
 *     $statement->parameters[0]->mode === \SqlSemantics\Model\Definition\Routine\ParameterMode::Input // => true
 *     $statement->parameters[1]->mode->value // => 'INOUT'
 */
final class ProcedureParameter
{
    /**
     * Accepts IN, OUT and INOUT parameters with a nonempty name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly DeclaredDomain $domain, public readonly ParameterMode $mode = ParameterMode::Input)
    {
        if ($name === '' || !in_array($mode, [ParameterMode::Input, ParameterMode::Output, ParameterMode::InputOutput], true)) {
            throw new InvalidStructure('A stored procedure parameter requires a name and an IN, OUT or INOUT direction.');
        }
    }
}

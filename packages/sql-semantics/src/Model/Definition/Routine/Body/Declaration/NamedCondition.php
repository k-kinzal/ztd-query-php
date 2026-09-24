<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A handled condition named by an enclosing DECLARE ... CONDITION.
 * @visibility public
 * @example Reading a named handled condition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR SQLSTATE \'42S02\'; DECLARE EXIT HANDLER FOR gone BEGIN END; END');
 *     $statement->body->declarations[1]->conditions[0]->name // => 'gone'
 */
final class NamedCondition
{
    /**
     * Requires the condition's name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A named condition requires a name.');
        }
    }
}

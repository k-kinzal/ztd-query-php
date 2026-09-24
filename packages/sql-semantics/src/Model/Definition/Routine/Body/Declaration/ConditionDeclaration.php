<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * DECLARE name CONDITION FOR value: names an error number or SQLSTATE for handlers and SIGNAL.
 * @visibility public
 * @example Reading a named SQLSTATE
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE PROCEDURE p() BEGIN DECLARE missing CONDITION FOR SQLSTATE '42S02'; END");
 *     $statement->body->declarations[0]->value->code // => '42S02'
 */
final class ConditionDeclaration
{
    /**
     * Requires the condition's name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly ErrorCode|SqlState $value)
    {
        if ($name === '') {
            throw new InvalidStructure('A condition declaration requires a name.');
        }
    }
}

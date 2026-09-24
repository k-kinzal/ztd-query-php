<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * ITERATE label: starts the next pass of the enclosing loop with that label.
 * @visibility public
 * @example Reading the restarted loop
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() again: LOOP ITERATE again; END LOOP');
 *     $statement->body->statements[0]->label // => 'again'
 */
final class IterateStatement implements ProgramStatement
{
    /**
     * Requires the loop label's name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $label)
    {
        if ($label === '') {
            throw new InvalidStructure('ITERATE requires a label.');
        }
    }
}

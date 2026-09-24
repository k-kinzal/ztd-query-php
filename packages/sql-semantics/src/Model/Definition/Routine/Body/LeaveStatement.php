<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * LEAVE label: exits the enclosing block or loop with that label.
 * @visibility public
 * @example Reading the exited label
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() done: BEGIN LEAVE done; END');
 *     $statement->body->statements[0]->label // => 'done'
 */
final class LeaveStatement implements ProgramStatement
{
    /**
     * Requires the label's name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $label)
    {
        if ($label === '') {
            throw new InvalidStructure('LEAVE requires a label.');
        }
    }
}

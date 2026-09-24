<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

/**
 * One statement of a stored program body: a compound or flow-control construct, or an ordinary statement run from the body.
 * @visibility public
 * @example Reading a stored procedure body
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
 *     $statement->body instanceof \SqlSemantics\Model\Definition\Routine\Body\ProgramStatement // => true
 */
interface ProgramStatement
{
}

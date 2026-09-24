<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

/**
 * A class of conditions a handler catches: warnings, no-data conditions, or all other errors.
 * @visibility public
 * @example Reading a handled condition class
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN END; END');
 *     $statement->body->declarations[0]->conditions[0] === \SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionClass::NotFound // => true
 */
enum ConditionClass: string
{
    case SqlWarning = 'SQLWARNING';
    case NotFound = 'NOT FOUND';
    case SqlException = 'SQLEXCEPTION';
}

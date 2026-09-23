<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Characteristics;

/**
 * Declares how a stored routine uses SQL data.
 * @visibility public
 * @example Inspecting a declared routine property
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER FUNCTION f READS SQL DATA');
 *     $statement->changes->dataAccess === \SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess::Reads // => true
 */
enum SqlDataAccess: string
{
    case None = 'NO SQL';
    case Contains = 'CONTAINS SQL';
    case Reads = 'READS SQL DATA';
    case Modifies = 'MODIFIES SQL DATA';
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\View;

/**
 * The MySQL view processing algorithm requested by the declaration.
 * @visibility public
 * @example Inspecting the requested algorithm
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE ALGORITHM=TEMPTABLE VIEW v AS SELECT 1');
 *     $statement->properties->algorithm === \SqlSemantics\Model\Definition\View\ViewAlgorithm::TempTable // => true
 */
enum ViewAlgorithm: string
{
    case Undefined = 'UNDEFINED';
    case Merge = 'MERGE';
    case TempTable = 'TEMPTABLE';
}

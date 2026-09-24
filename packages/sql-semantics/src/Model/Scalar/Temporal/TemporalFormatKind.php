<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * The temporal value kind whose format string MySQL's GET_FORMAT returns; TIMESTAMP is a synonym of DATETIME.
 * @visibility public
 * @example Reading the requested format kind
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT GET_FORMAT(TIMESTAMP, 'ISO')");
 *     $statement->outputs[0]->expression->temporalKind // => \SqlSemantics\Model\Scalar\Temporal\TemporalFormatKind::Datetime
 */
enum TemporalFormatKind: string
{
    case Date = 'DATE';
    case Time = 'TIME';
    case Datetime = 'DATETIME';
}

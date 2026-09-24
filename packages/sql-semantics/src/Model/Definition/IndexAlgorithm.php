<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * Requested algorithm for a MySQL index operation.
 * @visibility public
 * @example Reading the requested algorithm
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE', strict: false);
 *     $statement->algorithm // => \SqlSemantics\Model\Definition\IndexAlgorithm::Inplace
 */
enum IndexAlgorithm: string
{
    case Default = 'DEFAULT';
    case Inplace = 'INPLACE';
    case Copy = 'COPY';
}

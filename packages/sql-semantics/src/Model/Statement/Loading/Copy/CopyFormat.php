<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

/**
 * The data format of COPY.
 * @visibility public
 * @example Reading the format
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COPY t TO STDOUT CSV', strict: false);
 *     $statement->options->format === \SqlSemantics\Model\Statement\Loading\Copy\CopyFormat::Csv // => true
 */
enum CopyFormat: string
{
    case Text = 'text';
    case Csv = 'csv';
    case Binary = 'binary';
}

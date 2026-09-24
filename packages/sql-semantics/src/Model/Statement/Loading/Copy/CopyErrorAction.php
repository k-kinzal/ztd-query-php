<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

/**
 * The ON_ERROR behavior of COPY FROM when a value cannot be converted.
 * @visibility public
 * @example Reading the requested behavior
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COPY t FROM STDIN (ON_ERROR ignore)", strict: false);
 *     $statement->options->onError === \SqlSemantics\Model\Statement\Loading\Copy\CopyErrorAction::Ignore // => true
 */
enum CopyErrorAction: string
{
    case Stop = 'stop';
    case Ignore = 'ignore';
}

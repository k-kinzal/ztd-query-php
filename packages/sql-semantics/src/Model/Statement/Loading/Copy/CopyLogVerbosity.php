<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

/**
 * The LOG_VERBOSITY of COPY.
 * @visibility public
 * @example Reading the verbosity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COPY t FROM STDIN (LOG_VERBOSITY verbose)", strict: false);
 *     $statement->options->logVerbosity === \SqlSemantics\Model\Statement\Loading\Copy\CopyLogVerbosity::Verbose // => true
 */
enum CopyLogVerbosity: string
{
    case Default = 'default';
    case Verbose = 'verbose';
}

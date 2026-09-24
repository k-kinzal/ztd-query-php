<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

/**
 * Applies a COPY FORCE option to every copied column, written as an asterisk.
 * @visibility public
 * @example Reading the choice
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COPY t FROM STDIN (FORMAT csv, FORCE_NULL *)', strict: false);
 *     $statement->options->forceNull instanceof \SqlSemantics\Model\Statement\Loading\Copy\EveryColumn // => true
 */
final class EveryColumn implements ColumnChoice
{
}

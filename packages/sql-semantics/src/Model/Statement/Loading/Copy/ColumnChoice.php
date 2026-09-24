<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

/**
 * The columns a COPY FORCE_QUOTE, FORCE_NOT_NULL or FORCE_NULL option applies to: every copied column or listed ones.
 * @visibility public
 * @example Reading a column choice
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COPY t TO STDOUT CSV FORCE QUOTE *', strict: false);
 *     $statement->options->forceQuote instanceof \SqlSemantics\Model\Statement\Loading\Copy\EveryColumn // => true
 */
interface ColumnChoice
{
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

/**
 * VACUUM INDEX_CLEANUP requests; an unspecified request uses the table's vacuum_index_cleanup setting.
 * @visibility public
 * @example Reading an explicit request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('VACUUM (INDEX_CLEANUP off)');
 *     $statement->options->indexCleanup === \SqlSemantics\Model\Statement\Maintenance\PostgreSql\IndexCleanup::Off // => true
 */
enum IndexCleanup: string
{
    case Auto = 'AUTO';
    case On = 'ON';
    case Off = 'OFF';
}

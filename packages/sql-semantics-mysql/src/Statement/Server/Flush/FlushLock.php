<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Flush;

/**
 * The lock FLUSH TABLES takes after it closes the tables.
 *
 * Mirrors REFRESH_READ_LOCK and REFRESH_FOR_EXPORT. Each case holds the
 * keywords it is written with. FOR EXPORT needs a table list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock::ForExport->value // => 'FOR EXPORT'
 */
enum FlushLock: string
{
    case WithReadLock = 'WITH READ LOCK';
    case ForExport = 'FOR EXPORT';
}

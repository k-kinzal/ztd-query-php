<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

/**
 * A transaction access mode of SET TRANSACTION.
 *
 * Each case holds its keywords.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html#set-transaction-access-mode.
 *
 * @visibility public
 * @example Reading the keywords of a mode
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode::ReadOnly->value // => 'READ ONLY'
 */
enum AccessMode: string
{
    case ReadWrite = 'READ WRITE';
    case ReadOnly = 'READ ONLY';
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

/**
 * The FULL and EXTENDED keywords of SHOW TABLES and SHOW COLUMNS.
 *
 * FULL adds columns (the table type; the collation, privileges and comment
 * of a column). EXTENDED, from MySQL 8.0 on, also lists the hidden tables
 * and columns the server uses internally. Each case holds its keywords.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-columns.html.
 *
 * @visibility public
 * @example Reading the keywords of a listing
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing::ExtendedFull->value // => 'EXTENDED FULL'
 */
enum ShowListing: string
{
    case Full = 'FULL';
    case Extended = 'EXTENDED';
    case ExtendedFull = 'EXTENDED FULL';

    /**
     * Tells whether the listing adds the FULL columns.
     *
     * @example Telling whether EXTENDED adds columns
     *     \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing::Extended->full() // => false
     */
    public function full(): bool
    {
        return $this !== self::Extended;
    }
}

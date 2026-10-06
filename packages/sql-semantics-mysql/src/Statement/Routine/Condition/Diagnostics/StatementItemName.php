<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics;

/**
 * The statement information items GET DIAGNOSTICS reads: NUMBER and ROW_COUNT.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/diagnostics-area.html#diagnostics-area-information-items.
 *
 * @visibility public
 * @example Reading the keyword of an item
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName::RowCount->value // => 'ROW_COUNT'
 */
enum StatementItemName: string
{
    case Number = 'NUMBER';
    case RowCount = 'ROW_COUNT';
}

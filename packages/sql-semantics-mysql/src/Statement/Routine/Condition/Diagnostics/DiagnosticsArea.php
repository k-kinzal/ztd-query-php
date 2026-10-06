<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics;

/**
 * The diagnostics area GET DIAGNOSTICS reads: CURRENT or STACKED.
 *
 * Each case holds its keyword. Without a keyword the current area is read;
 * the model keeps whether the keyword is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 *
 * @visibility public
 * @example Reading the keyword of an area
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea::Stacked->value // => 'STACKED'
 */
enum DiagnosticsArea: string
{
    case Current = 'CURRENT';
    case Stacked = 'STACKED';
}

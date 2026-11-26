<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

/**
 * The written choice between all rows and distinct rows, in a selection or in the arguments of a function.
 *
 * Source: https://sqlite.org/lang_select.html#simple_select_processing,
 * https://sqlite.org/lang_aggfunc.html.
 *
 * @visibility SqlSemantics
 */
enum SetQuantifier: string
{
    case Distinct = 'DISTINCT';
    case All = 'ALL';
}

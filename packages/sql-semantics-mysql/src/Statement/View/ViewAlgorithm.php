<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

/**
 * How the server processes a view: ALGORITHM = UNDEFINED, MERGE or TEMPTABLE.
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/view-algorithms.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::TempTable->value // => 'TEMPTABLE'
 */
enum ViewAlgorithm: string
{
    case Undefined = 'UNDEFINED';
    case Merge = 'MERGE';
    case TempTable = 'TEMPTABLE';
}

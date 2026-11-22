<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

/**
 * The keywords SQLite accepts before JOIN.
 *
 * Source: https://sqlite.org/lang_select.html#the_from_clause.
 *
 * @visibility SqlSemantics
 */
enum JoinKeyword: string
{
    case Natural = 'NATURAL';
    case Left = 'LEFT';
    case Outer = 'OUTER';
    case Right = 'RIGHT';
    case Full = 'FULL';
    case Inner = 'INNER';
    case Cross = 'CROSS';
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

/**
 * The keywords SQLite accepts before JOIN.
 *
 * Source: https://sqlite.org/lang_select.html#the_from_clause.
 *
 * @visibility public
 * @example Reading the keywords of a join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM t LEFT OUTER JOIN u');
 *     $query->statement->from->steps[0]->operator->words // => [\SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword::Left, \SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword::Outer]
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

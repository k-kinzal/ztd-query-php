<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

/**
 * The rules about the clauses of a function call that SQLite enforces by the kind of the function.
 *
 * Source: https://sqlite.org/lang_aggfunc.html, https://sqlite.org/windowfunctions.html.
 *
 * @visibility public
 * @example Reading which rule a call breaks
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT row_number()');
 *     $query->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuseRule::WindowWithoutOver
 */
enum CallMisuseRule
{
    case WindowWithoutOver;
    case ScalarAsWindow;
    case FilterWithoutAggregate;
    case FilterOnWindowOnly;
    case OrderByWithoutAggregate;
    case DistinctInWindow;
    case DistinctArguments;
}

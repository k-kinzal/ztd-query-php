<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Lexical;

/**
 * How a word is delimited at a position where SQLite reads the written text and not only the name.
 *
 * Source: https://sqlite.org/lang_keywords.html.
 *
 * @visibility public
 * @example Reading how a type word is quoted
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a "big int")');
 *     $create->statement->columns[0]->type->words[0]->quote // => \SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote::Double
 */
enum WordQuote
{
    case Bare;
    case Single;
    case Double;
    case Backtick;
    case Bracket;
}

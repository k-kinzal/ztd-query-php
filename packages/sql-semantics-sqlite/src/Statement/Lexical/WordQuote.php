<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Lexical;

/**
 * How a word is delimited at a position where SQLite reads the written text and not only the name.
 *
 * Source: https://sqlite.org/lang_keywords.html.
 *
 * @visibility SqlSemantics
 */
enum WordQuote
{
    case Bare;
    case Single;
    case Double;
    case Backtick;
    case Bracket;
}

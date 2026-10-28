<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * The delimiter or unquoted keyword form of a name, independent of its decoded value.
 *
 * Keyword keeps a bare keyword distinct from an ordinary bare identifier.
 * Its lexical boundary can matter before parentheses or a qualification dot.
 * No token, parser rule or original text fragment is retained.
 *
 * @visibility public
 * @example Distinguishing a bare name from a backtick-delimited name
 *     \SqlSemantics\Statement\Identifier\NameQuote::Bare !== \SqlSemantics\Statement\Identifier\NameQuote::Backtick // => true
 */
enum NameQuote
{
    case Bare;
    case Keyword;
    case Backtick;
    case DoubleQuote;
    case SingleQuote;
}

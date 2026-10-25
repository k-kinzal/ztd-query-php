<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * The delimiter choice of an identifier, independent of its decoded value.
 *
 * @visibility public
 * @example Distinguishing a bare name from a backtick-delimited name
 *     \SqlSemantics\Statement\Identifier\NameQuote::Bare !== \SqlSemantics\Statement\Identifier\NameQuote::Backtick // => true
 */
enum NameQuote
{
    case Bare;
    case Backtick;
    case DoubleQuote;
}

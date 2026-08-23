<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Lexical categories needed for lossless SQL structure inspection.
 */
enum SqlTokenKind: string
{
    case Word = 'word';
    case QuotedIdentifier = 'quoted_identifier';
    case String = 'string';
    case Number = 'number';
    case Parameter = 'parameter';
    case Symbol = 'symbol';
    case Comment = 'comment';
    case Whitespace = 'whitespace';
}

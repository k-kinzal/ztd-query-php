<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

/**
 * Enumerates token kinds emitted by the MySQL Bison lexer.
 */
enum BisonTokenType
{
    case Directive;
    case Identifier;
    case Number;
    case CharLiteral;
    case StringLiteral;
    case TypeTag;

    case Colon;
    case Semicolon;
    case Pipe;
    case PercentPercent;

    case Prologue;
    case Action;

    case Eof;
}

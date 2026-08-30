<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Lexer;

/**
 * The kinds of lexeme a Bison grammar file is written from.
 */
enum BisonLexeme
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

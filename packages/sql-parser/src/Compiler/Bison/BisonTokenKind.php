<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

/**
 * The kinds of token a Bison grammar file is made of.
 *
 * @visibility root
 */
enum BisonTokenKind
{
    case Identifier;
    case CharLiteral;
    case String;
    case Number;
    case Directive;
    case Tag;
    case Code;
    case Colon;
    case Pipe;
    case Semicolon;
    case BracketOpen;
    case BracketClose;
    case Section;
    case Other;
}

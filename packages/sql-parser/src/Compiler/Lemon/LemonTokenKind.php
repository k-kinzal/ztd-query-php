<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

/**
 * The kinds of token a Lemon grammar file is made of.
 *
 * @visibility root
 */
enum LemonTokenKind
{
    case Identifier;
    case Directive;
    case Code;
    case Alias;
    case PrecedenceMark;
    case Arrow;
    case Dot;
    case Pipe;
    case String;
    case Number;
    case Other;
}

<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * How a symbol was written on the right-hand side of a rule.
 */
enum BisonSymbolType
{
    case Identifier;
    case CharLiteral;
}

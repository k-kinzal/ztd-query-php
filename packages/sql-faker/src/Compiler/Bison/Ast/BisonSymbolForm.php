<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Ast;

/**
 * How a symbol was written on the right-hand side of a rule.
 */
enum BisonSymbolForm
{
    case Identifier;
    case CharLiteral;
}

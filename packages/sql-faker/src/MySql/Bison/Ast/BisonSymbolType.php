<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Enumerates symbol kinds used in the MySQL Bison AST.
 */
enum BisonSymbolType
{
    case Identifier;
    case CharLiteral;
}

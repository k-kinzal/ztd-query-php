<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

enum BisonSymbolType
{
    case Identifier;
    case CharLiteral;
}

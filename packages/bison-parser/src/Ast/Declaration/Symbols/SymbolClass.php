<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration\Symbols;

/**
 * Which directive declared a list of symbols: `%token`, `%nterm` or `%type`.
 *
 * `%term` is the deprecated spelling of `%token` and reads as it.
 *
 * @visibility public
 *
 * @example Telling the directives apart
 *     \BisonParser\Ast\Declaration\Symbols\SymbolClass::from('nterm')->name // => 'Nonterminal'
 */
enum SymbolClass: string
{
    case Token = 'token';
    case Nonterminal = 'nterm';
    case Type = 'type';
}

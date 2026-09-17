<?php

declare(strict_types=1);

namespace BisonParser\Ast;

/**
 * The three spellings a grammar symbol may have.
 *
 * An identifier names a token or a nonterminal, a character literal such as
 * `'+'` is a token that stands for itself, and a string such as `"end of
 * file"` is a token spelled by its alias.
 *
 * @visibility public
 *
 * @example Telling the spellings apart
 *     \BisonParser\Ast\SymbolKind::CharLiteral->value // => 'char'
 */
enum SymbolKind: string
{
    case Identifier = 'identifier';
    case CharLiteral = 'char';
    case String = 'string';
}

<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration\Symbols;

/**
 * The four precedence directives: `%left`, `%right`, `%nonassoc` and `%precedence`.
 *
 * `%binary` is the deprecated spelling of `%nonassoc` and reads as it.
 *
 * @visibility public
 *
 * @example Telling the directives apart
 *     \BisonParser\Ast\Declaration\Symbols\Associativity::from('nonassoc')->name // => 'NonAssoc'
 */
enum Associativity: string
{
    case Left = 'left';
    case Right = 'right';
    case NonAssoc = 'nonassoc';
    case Precedence = 'precedence';
}

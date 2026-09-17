<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

/**
 * Which of the parser, the lexer, or both a `%param` directive adds arguments to.
 *
 * @visibility public
 *
 * @example Telling the directives apart
 *     \BisonParser\Ast\Declaration\ParamKind::from('lex-param')->name // => 'Lex'
 */
enum ParamKind: string
{
    case Both = 'param';
    case Lex = 'lex-param';
    case Parse = 'parse-param';
}

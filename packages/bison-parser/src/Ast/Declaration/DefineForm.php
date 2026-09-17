<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

/**
 * The three forms a `%define` value may take: a bare keyword, a string, or braced code.
 *
 * @visibility public
 *
 * @example Telling the forms apart
 *     \BisonParser\Ast\Declaration\DefineForm::Code->value // => 'code'
 */
enum DefineForm: string
{
    case Keyword = 'keyword';
    case String = 'string';
    case Code = 'code';
}

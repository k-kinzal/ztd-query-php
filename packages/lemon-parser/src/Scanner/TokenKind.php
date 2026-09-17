<?php

declare(strict_types=1);

namespace LemonParser\Scanner;

/**
 * The shapes of token Lemon's scanner produces.
 *
 * @visibility root
 */
enum TokenKind: string
{
    case Word = 'word';
    case String = 'string';
    case Code = 'braced code';
    case Arrow = '::=';
    case Compound = 'compound token';
    case Punctuation = 'punctuation';
    case End = 'end of file';
}

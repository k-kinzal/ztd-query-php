<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

/**
 * How the argument of a declaration was written.
 *
 * Lemon accepts a `{ code }` block, a `"string"` or a bare word after the
 * keyword and strips only the delimiters, so the tree records which form
 * it was to write it back the same way.
 *
 * @visibility public
 *
 * @example Reading the forms
 *     $file = (new \LemonParser\Parser())->parse("%name Calc\n%token_prefix \"TK_\"\n%include { int x; }\n");
 *     array_map(static fn ($d) => $d->form->value, $file->declarations()) // => ['word', 'string', 'code']
 */
enum ArgumentForm: string
{
    case Code = 'code';
    case String = 'string';
    case Word = 'word';
}

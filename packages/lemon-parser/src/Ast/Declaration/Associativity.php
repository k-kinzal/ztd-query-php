<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

/**
 * How a precedence declaration resolves a tie between a shift and a reduce.
 *
 * @visibility public
 *
 * @example Reading the associativity of a declaration
 *     $file = (new \LemonParser\Parser())->parse("%nonassoc EQ NE.\n");
 *     $file->declarations()[0]->associativity->value // => "nonassoc"
 */
enum Associativity: string
{
    case Left = 'left';
    case Right = 'right';
    case NonAssoc = 'nonassoc';
}

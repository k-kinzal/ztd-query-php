<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;

/**
 * A keyword with one argument, such as `%name`, `%include` or `%extra_argument`.
 *
 * Lemon appends the arguments of repeated keywords; the tree keeps each
 * occurrence where it is written.
 *
 * @visibility public
 *
 * @example Reading a directive
 *     $file = (new \LemonParser\Parser())->parse("%extra_argument {Parse *pParse}\n");
 *     $directive = $file->declarations()[0];
 *     [$directive->keyword->value, $directive->value, $directive->form->value] // => ['extra_argument', 'Parse *pParse', 'code']
 */
final class Directive implements Declaration
{
    /**
     * @param DirectiveKeyword $keyword The keyword
     * @param string $value The argument without its delimiters
     * @param ArgumentForm $form How the argument was written
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly DirectiveKeyword $keyword,
        public readonly string $value,
        public readonly ArgumentForm $form,
        public readonly Location $location,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function location(): Location
    {
        return $this->location;
    }
}

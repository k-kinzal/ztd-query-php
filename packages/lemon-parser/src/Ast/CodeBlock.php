<?php

declare(strict_types=1);

namespace LemonParser\Ast;

/**
 * Host code written between braces, kept as written.
 *
 * The braces are not part of the code. Lemon never interprets the code
 * beyond finding its closing brace, and neither does the tree.
 *
 * @visibility public
 *
 * @example Reading the action of a rule
 *     $file = (new \LemonParser\Parser())->parse("expr(A) ::= NUM(B). { A = B; }\n");
 *     $file->rules()[0]->code?->code // => " A = B; "
 */
final class CodeBlock
{
    /**
     * @param string $code The text between the braces
     * @param Location $location Where the opening brace is
     */
    public function __construct(
        public readonly string $code,
        public readonly Location $location,
    ) {
    }
}

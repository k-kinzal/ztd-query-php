<?php

declare(strict_types=1);

namespace LemonParser\Printer;

use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Rule;

/**
 * Writes a tree back as a grammar file Lemon reads to the same tree.
 *
 * Every rule and declaration goes on a line of its own, in file order.
 * Printing is stable: the printed text reads back to a tree that prints
 * the same.
 *
 * @visibility public
 *
 * @example Printing a grammar back
 *     $file = (new \LemonParser\Parser())->parse("%left PLUS.\nexpr(A) ::= expr(B) PLUS expr(C). [PLUS] { A = B + C; }  // add\n");
 *     (new \LemonParser\Printer\Printer())->print($file) // => "%left PLUS.\nexpr(A) ::= expr(B) PLUS expr(C). [PLUS] { A = B + C; }\n"
 */
final class Printer
{
    /**
     * @param DeclarationPrinter $declarations Writes declarations
     * @param RulePrinter $rules Writes rules
     */
    public function __construct(
        private readonly DeclarationPrinter $declarations = new DeclarationPrinter(),
        private readonly RulePrinter $rules = new RulePrinter(),
    ) {
    }

    /**
     * Writes the whole file.
     *
     * @param GrammarFile $file The tree
     *
     * @return string The grammar file, one item per line
     */
    public function print(GrammarFile $file): string
    {
        $text = '';
        foreach ($file->items as $item) {
            $text .= ($item instanceof Rule ? $this->rules->print($item) : $this->declarations->print($item)) . "\n";
        }

        return $text;
    }
}

<?php

declare(strict_types=1);

namespace LemonParser\Ast;

use LemonParser\Ast\Declaration\Declaration;

/**
 * A whole grammar file: rules and declarations in the order written.
 *
 * Lemon has no sections; a declaration may appear anywhere, and precedence
 * declarations rank tokens by the order they are written in, so the order
 * is kept.
 *
 * @visibility public
 *
 * @example Reading rules and declarations
 *     $file = (new \LemonParser\Parser())->parse("%left PLUS MINUS.\nexpr ::= expr PLUS expr.\n%name Calc\n");
 *     [count($file->items), count($file->rules()), count($file->declarations())] // => [3, 1, 2]
 */
final class GrammarFile
{
    /**
     * @param list<Rule|Declaration> $items Everything in the file, in order
     */
    public function __construct(
        public readonly array $items,
    ) {
    }

    /**
     * Lists the rules in order.
     *
     * @return list<Rule> The rules
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->items as $item) {
            if ($item instanceof Rule) {
                $rules[] = $item;
            }
        }

        return $rules;
    }

    /**
     * Lists the declarations in order.
     *
     * @return list<Declaration> The declarations
     */
    public function declarations(): array
    {
        $declarations = [];
        foreach ($this->items as $item) {
            if ($item instanceof Declaration) {
                $declarations[] = $item;
            }
        }

        return $declarations;
    }
}

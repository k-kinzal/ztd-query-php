<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;

/**
 * One rule of the grammar: a nonterminal and the alternatives that derive it.
 *
 * @visibility public
 *
 * @example Reading a rule
 *     $file = (new \BisonParser\Parser())->parse("%%\nexpr[e]: NUM | expr '+' expr ;\n");
 *     $rule = $file->rules()[0];
 *     $rule->name->value // => 'expr'
 *     $rule->namedReference // => 'e'
 *     count($rule->alternatives) // => 2
 */
final class Rule
{
    /**
     * @param Symbol $name The nonterminal on the left-hand side
     * @param string|null $namedReference The `[name]` after the nonterminal, or null when none is given
     * @param list<Alternative> $alternatives The right-hand sides in file order, at least one
     * @param Location $location Where the rule is written
     */
    public function __construct(
        public readonly Symbol $name,
        public readonly ?string $namedReference,
        public readonly array $alternatives,
        public readonly Location $location,
    ) {
    }
}

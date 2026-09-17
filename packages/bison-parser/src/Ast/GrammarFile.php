<?php

declare(strict_types=1);

namespace BisonParser\Ast;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Rule\Rule;

/**
 * A whole Bison grammar file: the declarations, the grammar, and the epilogue.
 *
 * The declarations are everything before the first `%%`. The grammar is
 * everything between the two `%%`, in file order; Bison lets grammar
 * declarations such as `%type` appear there among the rules, so the list
 * holds both. The epilogue is the text after the second `%%`, if any.
 *
 * @visibility public
 *
 * @example Walking the rules of a file
 *     $file = (new \BisonParser\Parser())->parse("%token NUM\n%%\nexpr: NUM | expr '+' NUM ;\n");
 *     count($file->rules()) // => 1
 *     $file->rules()[0]->name->value // => 'expr'
 */
final class GrammarFile
{
    /**
     * @param list<Declaration> $declarations Declarations before the first `%%`, in file order
     * @param list<Rule|Declaration> $grammar Rules and declarations between the two `%%`, in file order
     * @param Epilogue|null $epilogue Text after the second `%%`, or null when there is none
     */
    public function __construct(
        public readonly array $declarations,
        public readonly array $grammar,
        public readonly ?Epilogue $epilogue,
    ) {
    }

    /**
     * Answers the rules of the grammar section, in file order.
     *
     * @return list<Rule> The rules
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->grammar as $item) {
            if ($item instanceof Rule) {
                $rules[] = $item;
            }
        }

        return $rules;
    }

    /**
     * Answers every declaration, those of the declarations section first and those among the rules after.
     *
     * @return list<Declaration> The declarations in file order
     */
    public function allDeclarations(): array
    {
        $declarations = $this->declarations;
        foreach ($this->grammar as $item) {
            if ($item instanceof Declaration) {
                $declarations[] = $item;
            }
        }

        return $declarations;
    }
}

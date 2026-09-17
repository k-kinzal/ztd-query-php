<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration\Symbols;

use BisonParser\Ast\Symbol;

/**
 * One symbol of a `%token`, `%nterm`, `%type` or precedence declaration, with what was declared about it.
 *
 * @visibility public
 *
 * @example Reading a numbered token
 *     $file = (new \BisonParser\Parser())->parse("%token <str> IDENT 258 \"identifier\" NUM\n%%\ns: NUM;\n");
 *     $entries = $file->declarations[0]->entries;
 *     [$entries[0]->tag, $entries[0]->number, $entries[0]->alias?->text] // => ['str', 258, 'identifier']
 *     [$entries[1]->tag, $entries[1]->number, $entries[1]->alias] // => ['str', null, null]
 */
final class SymbolEntry
{
    /**
     * @param Symbol $symbol The symbol
     * @param string|null $tag The type tag in force, or null when none was given
     * @param int|null $number The token number, or null when none was given
     * @param Alias|null $alias The string alias, or null when none was given
     */
    public function __construct(
        public readonly Symbol $symbol,
        public readonly ?string $tag,
        public readonly ?int $number,
        public readonly ?Alias $alias,
    ) {
    }
}

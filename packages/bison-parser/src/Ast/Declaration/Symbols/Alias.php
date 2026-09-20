<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration\Symbols;

use BisonParser\Ast\Location;

/**
 * The string alias of a token, as in `%token PLUS "+"` or `%token END _("end of file")`.
 *
 * @visibility public
 *
 * @example Reading an alias
 *     $file = (new \BisonParser\Parser())->parse("%token END 0 _(\"end of file\")\n%%\ns: END;\n");
 *     $file->declarations[0]->entries[0]->alias?->text // => 'end of file'
 *     $file->declarations[0]->entries[0]->alias?->translatable // => true
 */
final class Alias
{
    /**
     * @param string $text The decoded string
     * @param bool $translatable Whether the alias was written as `_("...")`
     * @param Location $location Where the alias is written
     * @param string|null $spelling The alias as written, quotes and escapes included; null when built by hand
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $translatable,
        public readonly Location $location,
        public readonly ?string $spelling = null,
    ) {
    }
}

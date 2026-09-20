<?php

declare(strict_types=1);

namespace BisonParser\Ast;

/**
 * A reference to a grammar symbol as it is written in a declaration or rule.
 *
 * The value is the identifier itself, the decoded character of a character
 * literal, or the decoded text of a string alias, so `'\n'` and `"\n"` both
 * carry a newline.
 *
 * @visibility public
 *
 * Bison tells string tokens apart by their spelling, so `"\'"` and `"'"`
 * are two tokens; the spelling is kept for that, next to the decoded value.
 *
 * @example Reading a symbol
 *     $symbol = new \BisonParser\Ast\Symbol(\BisonParser\Ast\SymbolKind::CharLiteral, '+', new \BisonParser\Ast\Location(3, 9));
 *     $symbol->isIdentifier() // => false
 *     $symbol->value // => '+'
 */
final class Symbol
{
    /**
     * @param SymbolKind $kind How the symbol is spelled
     * @param string $value The identifier, the decoded character, or the decoded string
     * @param Location $location Where the symbol is written
     * @param string|null $spelling A literal as written, quotes and escapes included; null for an identifier
     */
    public function __construct(
        public readonly SymbolKind $kind,
        public readonly string $value,
        public readonly Location $location,
        public readonly ?string $spelling = null,
    ) {
    }

    /**
     * Reports whether the symbol is spelled as an identifier.
     *
     * @return bool True for an identifier, false for a character literal or string
     */
    public function isIdentifier(): bool
    {
        return $this->kind === SymbolKind::Identifier;
    }
}

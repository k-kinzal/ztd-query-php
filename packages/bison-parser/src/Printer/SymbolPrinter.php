<?php

declare(strict_types=1);

namespace BisonParser\Printer;

use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use BisonParser\Scanner\Escapes;

/**
 * Writes symbols, tags and literals the way Bison spells them.
 *
 * @visibility root
 */
final class SymbolPrinter
{
    /**
     * @param Escapes $escapes Writes the bytes of a literal
     */
    public function __construct(private readonly Escapes $escapes = new Escapes())
    {
    }

    /**
     * Writes a symbol.
     *
     * @param Symbol $symbol The symbol
     *
     * @return string The identifier, or the quoted literal
     */
    public function symbol(Symbol $symbol): string
    {
        return match ($symbol->kind) {
            SymbolKind::Identifier => $symbol->value,
            SymbolKind::CharLiteral => "'" . $this->escapes->encode($symbol->value, "'") . "'",
            SymbolKind::String => $this->string($symbol->value),
        };
    }

    /**
     * Writes a string literal.
     *
     * @param string $text The decoded text
     *
     * @return string The quoted literal
     */
    public function string(string $text): string
    {
        return '"' . $this->escapes->encode($text, '"') . '"';
    }

    /**
     * Writes a tag between angle brackets.
     *
     * @param Tag $tag The tag
     *
     * @return string The tag as written in a grammar
     */
    public function tag(Tag $tag): string
    {
        return '<' . $tag->name . '>';
    }

    /**
     * Writes braced code.
     *
     * @param string $code The code without its braces
     *
     * @return string The code between braces
     */
    public function code(string $code): string
    {
        return '{' . $code . '}';
    }
}

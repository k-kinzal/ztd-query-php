<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%fallback FALLBACK TOKEN TOKEN... .`: tokens the parser retries as the fallback when they cause an error.
 *
 * The first symbol is the fallback; the ones after it fall back to it.
 *
 * @visibility public
 *
 * @example Reading a fallback declaration
 *     $file = (new \LemonParser\Parser())->parse("%fallback ID ABORT AFTER.\n");
 *     $fallback = $file->declarations()[0];
 *     [$fallback->fallback()?->name, array_map(static fn ($symbol) => $symbol->name, $fallback->tokens())] // => ['ID', ['ABORT', 'AFTER']]
 */
final class Fallback implements Declaration
{
    /**
     * @param list<Symbol> $symbols The fallback first, then the tokens that fall back to it
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly array $symbols,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers the token the others fall back to.
     *
     * @return Symbol|null The first symbol, or null when none was written
     */
    public function fallback(): ?Symbol
    {
        return $this->symbols[0] ?? null;
    }

    /**
     * Lists the tokens that fall back.
     *
     * @return list<Symbol> Every symbol after the first
     */
    public function tokens(): array
    {
        return array_slice($this->symbols, 1);
    }

    /**
     * {@inheritDoc}
     */
    public function location(): Location
    {
        return $this->location;
    }
}

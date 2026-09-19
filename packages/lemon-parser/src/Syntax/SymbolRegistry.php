<?php

declare(strict_types=1);

namespace LemonParser\Syntax;

use LemonParser\Ast\Location;
use LemonParser\SyntaxException;

/**
 * Remembers what Lemon's symbol table would know while the file is read.
 *
 * Lemon rejects a second precedence, type, fallback or wildcard for a
 * symbol and a token class named after a symbol already seen; this keeps
 * enough to raise the same errors in the same places.
 *
 * @visibility root
 */
final class SymbolRegistry
{
    /**
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * @var array<string, true>
     */
    private array $ranked = [];

    /**
     * @var array<string, true>
     */
    private array $typed = [];

    /**
     * @var array<string, true>
     */
    private array $fallen = [];

    private ?string $wildcard = null;

    /**
     * Records a symbol as Lemon's `Symbol_new` does.
     *
     * @param string $name The symbol
     */
    public function see(string $name): void
    {
        $this->seen[$name] = true;
    }

    /**
     * Reports whether a symbol was seen, as Lemon's `Symbol_find` does.
     *
     * @param string $name The symbol
     *
     * @return bool True when seen before
     */
    public function isKnown(string $name): bool
    {
        return isset($this->seen[$name]);
    }

    /**
     * Records a precedence for a terminal.
     *
     * @param string $name The terminal
     * @param Location $location Where it is written
     *
     * @throws SyntaxException When the terminal already has one
     */
    public function rank(string $name, Location $location): void
    {
        $this->see($name);
        if (isset($this->ranked[$name])) {
            throw new SyntaxException("Symbol \"{$name}\" has already be given a precedence.", $location);
        }
        $this->ranked[$name] = true;
    }

    /**
     * Records a type for a symbol.
     *
     * @param string $name The symbol
     * @param Location $location Where it is written
     *
     * @throws SyntaxException When the symbol already has one
     */
    public function type(string $name, Location $location): void
    {
        if (isset($this->typed[$name])) {
            throw new SyntaxException("Symbol %type \"{$name}\" already defined", $location);
        }
        $this->see($name);
        $this->typed[$name] = true;
    }

    /**
     * Records that a token falls back to another.
     *
     * @param string $name The token
     * @param Location $location Where it is written
     *
     * @throws SyntaxException When the token already falls back
     */
    public function fallBack(string $name, Location $location): void
    {
        $this->see($name);
        if (isset($this->fallen[$name])) {
            throw new SyntaxException("More than one fallback assigned to token {$name}", $location);
        }
        $this->fallen[$name] = true;
    }

    /**
     * Records the wildcard token.
     *
     * @param string $name The token
     * @param Location $location Where it is written
     *
     * @throws SyntaxException When a wildcard was already named
     */
    public function wildcard(string $name, Location $location): void
    {
        $this->see($name);
        if ($this->wildcard !== null) {
            throw new SyntaxException("Extra wildcard to token: {$name}", $location);
        }
        $this->wildcard = $name;
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source;

use RuntimeException;
use SqlFaker\Grammar\Model\Grammar;

/**
 * Reports that grammar source text could not be turned into a grammar.
 *
 * Bison and Lemon grammars are read from files that ship with the server they
 * describe, so a grammar that declares tokens but no rules, or that ends before
 * its rules section, is a property of the input rather than a defect in the
 * parser. Callers that load grammars at runtime are expected to handle this.
 */
final class GrammarParseException extends RuntimeException
{
    /**
     * Reports grammar source that yielded no production rules.
     *
     * @param string $dialect Grammar flavour that was being read, e.g. "Bison"
     *
     * @return self Exception naming the grammar flavour
     */
    public static function noRulesParsed(string $dialect): self
    {
        return new self("No grammar rules parsed from the {$dialect} grammar.");
    }

    /**
     * Reports a grammar file that could not be read from disk.
     *
     * @param string $path Path that was asked for
     *
     * @return self Exception naming the path
     */
    public static function unreadableSource(string $path): self
    {
        return new self("Failed to read: {$path}");
    }

    /**
     * Reports a character that starts no lexeme of the grammar language.
     *
     * @param string $character The character that was reached
     * @param int $offset Where in the source it sits
     *
     * @return self Exception naming the character and its position
     */
    public static function unexpectedCharacter(string $character, int $offset): self
    {
        return new self("Unexpected character '{$character}' at offset {$offset}");
    }

    /**
     * Reports a slash that opens neither comment form.
     *
     * @param int $offset Where the slash sits
     *
     * @return self Exception naming the position
     */
    public static function danglingSlash(int $offset): self
    {
        return new self("Unexpected '/' at offset {$offset}");
    }

    /**
     * Reports a percent sign with no directive name after it.
     *
     * @param int $offset Where the percent sign sits
     *
     * @return self Exception naming the position
     */
    public static function namelessDirective(int $offset): self
    {
        return new self("Unexpected '%' at offset {$offset}");
    }

    /**
     * Reports a semantic type tag that is never closed.
     *
     * @param int $offset Where the tag opens
     *
     * @return self Exception naming the position the tag opened at
     */
    public static function unterminatedTypeTag(int $offset): self
    {
        return new self("Unterminated type tag starting at offset {$offset}");
    }

    /**
     * Reports a scanner that answered a token without consuming any input.
     *
     * Reading a token is what moves a reader through its source, so a scanner
     * that answers one without advancing leaves the reader asking the same
     * question forever. Saying so is the only way that ends.
     *
     * @param string $character Character the scanner was handed
     * @param int $offset Where it was handed it
     *
     * @return self Exception naming the character the scanner stopped on
     */
    public static function scannerDidNotAdvance(string $character, int $offset): self
    {
        return new self("Scanner read '{$character}' at offset {$offset} without consuming it");
    }
}

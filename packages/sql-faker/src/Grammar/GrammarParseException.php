<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

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
}

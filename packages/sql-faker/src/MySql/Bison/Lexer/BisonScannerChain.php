<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

/**
 * Chooses which scanner owns the character under the cursor.
 *
 * Holding the order in one place is what makes the lexer's dispatch a lookup
 * rather than a decision: no scanner needs to know which others exist, and the
 * grammar language can gain a lexeme by adding a scanner to the chain.
 *
 * @visibility root
 */
final class BisonScannerChain
{
    /**
     * @param list<BisonScanner> $scanners Scanners in the order they are consulted
     */
    public function __construct(private readonly array $scanners)
    {
    }

    /**
     * Builds the chain that reads GNU Bison and Yacc grammar files.
     *
     * The claimed characters do not overlap, so the order is documentation
     * rather than precedence.
     *
     * @return self A chain covering every lexeme of the grammar language
     */
    public static function forBisonGrammar(): self
    {
        return new self([
            new DirectiveScanner(),
            new ActionScanner(),
            new TypeTagScanner(),
            new QuotedLiteralScanner(),
            new NumberScanner(),
            new IdentifierScanner(),
            new PunctuationScanner(),
        ]);
    }

    /**
     * Finds the scanner that recognises a lexeme starting at the character.
     *
     * @param string $character A single character; never the empty string
     *
     * @return BisonScanner|null The first scanner that claims it, or null when none does
     */
    public function scannerFor(string $character): ?BisonScanner
    {
        foreach ($this->scanners as $scanner) {
            if ($scanner->handles($character)) {
                return $scanner;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use RuntimeException;

/**
 * Thrown when a symbol in the AST is neither a rule name nor a declared token.
 *
 * The AST is read from a grammar file this package generated from upstream
 * source, so a symbol nothing declares says the file and the parser that
 * wrote it disagree. That is a condition of the input, not a mistake in the
 * code reading it, so it is reported rather than declared.
 */
final class UnknownSymbolException extends RuntimeException
{
    /**
     * @param string $symbolName Symbol the grammar never declares
     */
    public function __construct(string $symbolName)
    {
        parent::__construct("Unknown symbol: {$symbolName}");
    }
}

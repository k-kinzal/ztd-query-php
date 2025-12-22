<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use Exception;

/**
 * Thrown when a symbol in the AST is neither a rule name nor a declared token.
 */
final class UnknownSymbolException extends Exception
{
    public function __construct(string $symbolName)
    {
        parent::__construct("Unknown symbol: {$symbolName}");
    }
}

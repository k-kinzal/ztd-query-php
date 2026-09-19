<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * A rule names a symbol that the grammar never declares.
 *
 * @visibility root
 */
final class UnknownSymbolException extends GrammarException
{
    /**
     * @param string $symbol The undeclared name
     */
    public function __construct(public readonly string $symbol)
    {
        parent::__construct("Unknown grammar symbol: {$symbol}");
    }
}

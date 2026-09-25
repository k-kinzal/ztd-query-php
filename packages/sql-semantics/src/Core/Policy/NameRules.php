<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Lexer\Token;

/**
 * Supplies identifier comparison and decoding.
 *
 * @visibility SqlSemantics
 */
interface NameRules
{
    /**
     * Decodes identifier quoting and applies the configured language case folding.
     */
    public function name(Token $token): string;

    /**
     * Compares column or correlation names using this dialect.
     */
    public function equal(string $left, string $right): bool;

    /**
     * Compares relation names under the default table name case policy.
     */
    public function relationEqual(string $left, string $right): bool;

    /**
     * Canonical comparison key for a column or correlation identifier.
     */
    public function key(string $name): string;
}

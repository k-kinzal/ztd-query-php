<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Lexer\Token;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Policy\NameRules as Contract;

/**
 * MySql NameRules implementation.
 *
 * @visibility SqlSemantics
 */
final class NameRules implements Contract
{
    /**
     * Decodes identifier quoting and applies MySql case folding.
     */
    public function name(Token $token): string
    {
        $text = $token->text;
        $quote = substr($text, 0, 1);
        if (in_array($quote, ['"', '`', '['], true) || $quote === "'") {
            $close = $quote === '[' ? ']' : $quote;
            return str_replace($close . $close, $close, substr($text, 1, -1));
        }
        return $text;
    }

    /**
     * Compares column or correlation names using this dialect.
     */
    public function equal(string $left, string $right): bool
    {
        return strcasecmp($left, $right) === 0;
    }

    /**
     * Compares relation names under the default table name case policy.
     */
    public function relationEqual(string $left, string $right): bool
    {
        return $left === $right;
    }

    /**
     * Canonical comparison key for a column or correlation identifier.
     */
    public function key(string $name): string
    {
        return strtolower($name);
    }
}

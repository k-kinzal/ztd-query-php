<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;

/**
 * Reads identifiers using token kinds and dialect-specific quoting and case rules.
 *
 * @visibility SqlSemantics
 */
final class Identifiers
{
    /**
     * Binds the dependencies used for this analysis.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Decodes identifier quoting and applies PostgreSQL case folding.
     */
    public function name(Token $token): string
    {
        $text = $token->text;
        $quote = substr($text, 0, 1);
        if (in_array($quote, ['"', '`', '['], true) || ($quote === "'" && $this->dialect !== Dialect::PostgreSql)) {
            $close = $quote === '[' ? ']' : $quote;
            return str_replace($close . $close, $close, substr($text, 1, -1));
        }

        return $this->dialect === Dialect::PostgreSql ? strtolower($text) : $text;
    }

    /**
     * @return list<string>
     */
    public function parts(Node $node): array
    {
        $parts = [];
        foreach ($node->tokens() as $token) {
            if ($token->text !== '.') {
                $parts[] = $this->name($token);
            }
        }

        return $parts;
    }

    /**
     * Compares column or correlation names using this dialect.
     */
    public function equal(string $left, string $right): bool
    {
        return $this->dialect === Dialect::PostgreSql ? $left === $right : strcasecmp($left, $right) === 0;
    }

    /**
     * Compares relation names under the default catalog case policy.
     */
    public function relationEqual(string $left, string $right): bool
    {
        return $this->dialect === Dialect::Sqlite ? strcasecmp($left, $right) === 0 : $left === $right;
    }

}

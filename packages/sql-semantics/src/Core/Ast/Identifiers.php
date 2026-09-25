<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;

/**
 * Reads identifiers using token kinds and dialect-specific quoting and case rules.
 *
 * @visibility SqlSemantics
 */
final class Identifiers
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Decodes identifier quoting and applies the supplied name policy.
     */
    public function name(Token $token): string
    {
        return $this->dialect->platform()->names()->name($token);
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
        return $this->dialect->platform()->names()->equal($left, $right);
    }

    /**
     * Compares relation names under the default table name case policy.
     */
    public function relationEqual(string $left, string $right): bool
    {
        return $this->dialect->platform()->names()->relationEqual($left, $right);
    }
}

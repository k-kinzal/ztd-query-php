<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * The comments of one parse tree, by the token each was written before.
 *
 * The comments before a node are those before its first token, which this
 * value finds without collecting the node's other tokens.
 *
 * @visibility SqlSemantics
 */
final class SourceComments
{
    /**
     * @param list<string> $leading Comments before the first token
     * @param array<int, list<string>> $before Comments by the object id of the token they precede
     * @param list<string> $trailing Comments after the last token that spells SQL
     */
    public function __construct(
        public readonly array $leading,
        private readonly array $before,
        public readonly array $trailing,
    ) {
    }

    /**
     * Answers the comments written before a token, in order.
     *
     * @return list<string>
     */
    public function before(Token $token): array
    {
        return $this->before[spl_object_id($token)] ?? [];
    }

    /**
     * Finds the first token below a node, or null when the node spells nothing.
     */
    public function first(Node $node): ?Token
    {
        foreach ($node->children as $child) {
            $token = $child instanceof Token ? $child : $this->first($child);
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }
}

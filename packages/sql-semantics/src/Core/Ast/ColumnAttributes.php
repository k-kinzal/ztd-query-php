<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Statement\Declaration\Nullability;

/**
 * Finds explicit NULL facts in complete attribute nodes, in writing order.
 * @visibility SqlSemantics
 */
final class ColumnAttributes
{
    /**
     * @param list<Node> $attributes
     * @return list<Nullability>
     */
    public static function nulls(array $attributes): array
    {
        $facts = [];
        foreach ($attributes as $attribute) {
            $tokens = $attribute->tokens();
            if (strtoupper($tokens[0]->text ?? '') === 'CONSTRAINT') {
                $tokens = array_slice($tokens, 2);
            }
            $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
            if (array_slice($words, 0, 2) === ['NOT', 'NULL']) {
                $facts[] = Nullability::NotNull;
            } elseif (($words[0] ?? '') === 'NULL') {
                $facts[] = Nullability::MaybeNull;
            }
        }
        return $facts;
    }
}

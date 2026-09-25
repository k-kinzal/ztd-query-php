<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Collects grammar-owned reductions that must be validated by reparsing.
 *
 * @visibility SqlFormatter
 */
final class Reductions
{
    /**
     * Excludes candidates that cross or carry directives.
     *
     * @param array<int, true> $protected
     * @return list<list<int>> Token offsets to omit together
     */
    public static function candidates(Node $tree, array $protected): array
    {
        $candidates = [...Grouping::pairs($tree), ...array_map(static fn (int $offset): array => [$offset], self::aliases($tree))];
        return array_values(array_filter($candidates, static fn (array $offsets): bool => array_filter(
            array_keys($protected),
            static fn (int $offset): bool => $offset >= min($offsets) && $offset <= max($offsets),
        ) === []));
    }

    /**
     * Finds AS only in alias productions, never inside CAST or CTE syntax.
     *
     * @return list<int>
     */
    public static function aliases(Node $node, ?Node $parent = null): array
    {
        $offsets = [];
        $owner = in_array($node->name, ['select_alias', 'target_el', 'alias_clause', 'as'], true)
            || (in_array($node->name, ['opt_as', 'table_alias'], true) && $parent?->name === 'opt_table_alias');
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                array_push($offsets, ...self::aliases($child, $node));
            } elseif ($owner && $child->name === 'AS') {
                $offsets[] = $child->offset;
            }
        }
        return $offsets;
    }
}

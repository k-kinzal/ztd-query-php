<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

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
     * @param array<string, string|null> $aliases Alias owners and optional required parents
     */
    public function __construct(private readonly Grouping $grouping, private readonly array $aliases)
    {
    }

    /**
     * Excludes candidates that cross or carry directives.
     *
     * @param array<int, true> $protected
     * @return list<list<int>> Token offsets to omit together
     */
    public function candidates(Node $tree, array $protected): array
    {
        $candidates = [...$this->grouping->pairs($tree), ...array_map(static fn (int $offset): array => [$offset], $this->aliases($tree))];
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
    public function aliases(Node $node, ?Node $parent = null): array
    {
        $offsets = [];
        $owner = array_key_exists($node->name, $this->aliases)
            && ($this->aliases[$node->name] === null || $parent?->name === $this->aliases[$node->name]);
        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                array_push($offsets, ...$this->aliases($child, $node));
            } elseif ($owner && $child->name === 'AS') {
                $offsets[] = $child->offset;
            }
        }
        return $offsets;
    }
}

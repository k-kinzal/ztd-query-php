<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transformation;

use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces an owned SQL production while preserving every unrelated component.
 *
 * @visibility SqlSemantics
 */
final class TreeEdit
{
    /**
     * @throws InvalidStructure
     */
    public static function replace(Tree $tree, Tree $target, Tree $replacement): Tree
    {
        if (!self::contains($tree, $target)) {
            throw new InvalidStructure('The SQL component does not belong to this statement.');
        }
        return self::rewrite($tree, $target, $replacement);
    }

    /**
     * Checks ownership by identity rather than matching text from a different statement.
     */
    public static function contains(Tree $tree, Tree $target): bool
    {
        if ($tree === $target) {
            return true;
        }
        foreach ($tree->children as $child) {
            if ($child instanceof Tree && self::contains($child, $target)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reuses unaffected immutable terminals.
     */
    public static function rewrite(Tree $tree, Tree $target, Tree $replacement): Tree
    {
        if ($tree === $target) {
            return $replacement;
        }
        return new Tree($tree->role, array_map(static fn ($child) => $child instanceof Tree ? self::rewrite($child, $target, $replacement) : $child, $tree->children));
    }
}

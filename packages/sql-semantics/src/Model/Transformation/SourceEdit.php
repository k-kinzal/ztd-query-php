<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transformation;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Sql\Source;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces owned productions or contiguous terminals, never SQL byte ranges.
 *
 * @visibility SqlSemantics
 */
final class SourceEdit
{
    /**
     * @throws InvalidStructure
     */
    public static function replace(Node|Token $source, Tree $tree, Node|Token $target, Tree $replacement): Tree
    {
        $direct = self::production($source, $tree, $target, $replacement);
        if ($direct !== null) {
            return $direct;
        }
        $tokens = $source instanceof Token ? [$source] : $source->tokens();
        $selected = $target instanceof Token ? [$target] : $target->tokens();
        $start = $selected === [] ? false : array_search($selected[0], $tokens, true);
        if ($start === false || array_slice($tokens, $start, count($selected)) !== $selected) {
            throw new InvalidStructure('The SQL component does not belong to this statement.');
        }
        return new Tree($tree->role, [
            ...array_map(Source::read(...), array_slice($tokens, 0, $start)),
            $replacement,
            ...array_map(Source::read(...), array_slice($tokens, $start + count($selected))),
            ...($source instanceof Node ? Source::annotations($source->trailing) : []),
        ]);
    }

    /**
     * Preserves empty optional grammar positions as well as ordinary productions.
     */
    public static function production(Node|Token $source, Tree $tree, Node|Token $target, Tree $replacement): ?Tree
    {
        if ($source === $target) {
            return $replacement;
        }
        if ($source instanceof Token) {
            return null;
        }
        foreach ($source->children as $index => $child) {
            $part = $tree->children[$index] ?? null;
            if (!$part instanceof Tree) {
                continue;
            }
            $changed = self::production($child, $part, $target, $replacement);
            if ($changed !== null) {
                $children = $tree->children;
                $children[$index] = $changed;
                return new Tree($tree->role, array_values($children));
            }
        }
        return null;
    }
}

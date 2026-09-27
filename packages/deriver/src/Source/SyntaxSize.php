<?php

declare(strict_types=1);

namespace Deriver\Source;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\SourceLimits;
use PhpParser\Node;

/**
 * Checks raw parser output iteratively before recursive name resolution and cloning.
 * @visibility root
 */
final class SyntaxSize
{
    /**
     * Rejects oversized or deeply nested syntax while measuring retention cost.
     * @param list<Node> $nodes Raw source tree
     * @param SourceLimits $limits Source admission policy
     * @return array{int, int} Node count and maximum structural depth
     * @throws InvalidInputException If syntax exceeds the finite admission limits
     */
    public function measure(array $nodes, SourceLimits $limits): array
    {
        /** @var list<array{Node, int}> $pending */
        $pending = [];
        foreach ($nodes as $node) {
            $pending[] = [$node, 1];
        }
        $count = 0;
        $maximum = 0;
        while ($pending !== []) {
            [$current, $depth] = array_pop($pending);
            if (++$count > $limits->nodes || $depth > $limits->depth) {
                throw new InvalidInputException('SOURCE_LIMIT: syntax size or nesting exceeds the admission limit.');
            }
            $maximum = max($maximum, $depth);
            foreach (get_object_vars($current) as $children) {
                foreach (is_array($children) ? $children : [$children] as $child) {
                    if ($child instanceof Node) {
                        $pending[] = [$child, $depth + 1];
                    }
                }
            }
        }
        return [$count, $maximum];
    }
}

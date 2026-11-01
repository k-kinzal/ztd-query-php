<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use WeakMap;

/**
 * Computes source ranges once per parse node during lowering.
 *
 * Only integer offsets are cached. Weak keys allow discarded parse trees to be collected,
 * and the cache is never included in a published semantic value.
 *
 * @visibility SqlSemantics
 */
final class SourceRanges
{
    /**
     * @var WeakMap<Node, array{?array{int, int}, ?array{int, int}}>
     */
    private readonly WeakMap $ranges;

    /**
     * Starts a transient range cache for one lowering.
     */
    public function __construct()
    {
        $this->ranges = new WeakMap();
    }

    /**
     * Answers the first token offset and last token end without flattening nested token lists.
     *
     * @return array{int, int}|null The range, or null when no selected token exists
     */
    public function span(Node $source, bool $includeEmpty = false): ?array
    {
        $pending = [$source];
        while ($pending !== []) {
            $node = $pending[array_key_last($pending)];
            if (isset($this->ranges[$node])) {
                array_pop($pending);
                continue;
            }
            $children = array_filter($node->children, fn (Node|Token $child): bool => $child instanceof Node && !isset($this->ranges[$child]));
            if ($children !== []) {
                array_push($pending, ...$children);
                continue;
            }
            $first = $last = $visibleFirst = $visibleLast = null;
            foreach ($node->children as $child) {
                [$visible, $all] = $child instanceof Token ? [$child->text === '' ? null : [$child->offset, $child->end()], [$child->offset, $child->end()]] : $this->ranges[$child];
                if ($all !== null) {
                    $first ??= $all[0];
                    $last = $all[1];
                }
                if ($visible !== null) {
                    $visibleFirst ??= $visible[0];
                    $visibleLast = $visible[1];
                }
            }
            $this->ranges[$node] = [$visibleFirst === null || $visibleLast === null ? null : [$visibleFirst, $visibleLast], $first === null || $last === null ? null : [$first, $last]];
            array_pop($pending);
        }

        return $this->ranges[$source][$includeEmpty ? 1 : 0];
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Expression;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Preserves derived operand grouping where the source grammar permits parenthesized expressions.
 * This also prevents non-associative operators from being serialized as forbidden flat chains.
 */
final class ExpressionGroupingRule implements RewriteRule
{
    /**
     * @param non-empty-list<string> $expressions Expression families with a parenthesized primary production
     */
    public function __construct(private readonly array $expressions, private readonly string $source, private readonly string $open = '(', private readonly string $close = ')')
    {
    }

    /**
     * Parenthesizes nested compound operands, retaining their original production and leaf identities.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $ranges = $this->ranges($sequence);
        $parents = [];
        $opens = [];
        $closes = [];
        $next = min([0, ...array_map(static fn (TerminalOccurrence $terminal): int => $terminal->id, $sequence->terminals)]) - 1;
        foreach ($sequence->productions as $production) {
            $parents[$production->id] = $production->rule;
            $range = $ranges[$production->id] ?? null;
            if (!in_array($production->rule, $this->expressions, true)
                || !in_array($parents[$production->parent ?? -1] ?? '', $this->expressions, true)
                || $range === null || $range[1] - $range[0] < 2) {
                continue;
            }
            [$start, $end] = $range;
            $anchor = $sequence->terminals[$start];
            if ($anchor->rewrite === $this->source) {
                continue;
            }
            $position = array_search($production->id, $anchor->ancestors, true);
            $ancestors = array_slice($anchor->ancestors, 0, ($position === false ? 0 : $position) + 1);
            $rules = array_slice($anchor->rules, 0, count($ancestors));
            $opens[$start][] = new TerminalOccurrence($this->open, $next--, $ancestors, $rules, $this->source);
            $closes[$end][] = new TerminalOccurrence($this->close, $next--, $ancestors, $rules, $this->source);
        }
        if ($opens === []) {
            return $sequence;
        }
        $result = [];
        foreach ($sequence->terminals as $index => $terminal) {
            array_push($result, ...($opens[$index] ?? []));
            $result[] = $terminal;
            array_push($result, ...array_reverse($closes[$index + 1] ?? []));
        }
        return $sequence->replace(0, count($sequence->terminals), $result, $this->source);
    }

    /**
     * Finds all source subtree ranges in one pass through retained ancestor identities.
     * @return array<int, array{int, int}>
     */
    public function ranges(TerminalSequence $sequence): array
    {
        $ranges = [];
        foreach ($sequence->terminals as $index => $terminal) {
            foreach ($terminal->ancestors as $ancestor) {
                $ranges[$ancestor] = [$ranges[$ancestor][0] ?? $index, $index + 1];
            }
        }
        return $ranges;
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\Coverage;

use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Separates selected grammar occurrences from source subtrees preserved in the emitted sequence.
 */
final class SequenceObservation
{
    /**
     * Groups leaf identities and names by every source production they descend from.
     * @param list<TerminalOccurrence> $terminals
     * @return array<int, list<array{int, string}>>
     */
    public function leaves(array $terminals): array
    {
        $result = [];
        foreach ($terminals as $terminal) {
            $leaf = [$terminal->id, $terminal->name];
            foreach ($terminal->ancestors as $ancestor) {
                $result[$ancestor][] = $leaf;
            }
        }
        return $result;
    }

    /**
     * Retains exact original subtrees; empty productions in transformed parents are conservatively uncredited.
     * @return list<int>
     */
    public function preserved(TerminalSequence $sequence): array
    {
        $before = $this->leaves($sequence->original);
        $after = $this->leaves($sequence->terminals);
        $preserved = [];
        foreach ($sequence->productions as $production) {
            if (($before[$production->id] ?? []) !== ($after[$production->id] ?? [])) {
                continue;
            }
            if (!isset($before[$production->id]) && $production->parent !== null && !isset($preserved[$production->parent])) {
                continue;
            }
            $preserved[$production->id] = true;
        }
        return array_keys($preserved);
    }
}

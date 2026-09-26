<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan\Compilation;

use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Model\Grammar;

/**
 * Restores source grammar identities before dialect rewrites and retains scoped lexical conditions.
 */
final class PreparedGrammar
{
    /**
     * @param array<string, array<int, array<string, LexemeConstraint>>> $lexemes
     */
    public function __construct(public readonly Grammar $grammar, private readonly array $lexemes)
    {
    }

    /**
     * The dialect sees only original productions and names, including their unfiltered ordinals.
     */
    public function restore(TerminalSequence $sequence): ScopedGeneration
    {
        $productions = [];
        $bindings = [];
        foreach ($sequence->productions as $production) {
            $bindings[$production->id] = $this->lexemes[$production->rule][$production->ordinal] ?? [];
            $productions[] = new ProductionOccurrence(
                $production->id,
                $production->parent,
                $this->grammar->sourceRule($production->rule),
                $this->grammar->sourceOrdinal($production->rule, $production->ordinal),
            );
        }
        $terminals = array_map(fn (TerminalOccurrence $terminal): TerminalOccurrence => new TerminalOccurrence(
            $terminal->name,
            $terminal->id,
            $terminal->ancestors,
            array_map($this->grammar->sourceRule(...), $terminal->rules),
            $terminal->rewrite,
        ), $sequence->terminals);
        return new ScopedGeneration(new TerminalSequence($terminals, $terminals, [], $productions), $bindings);
    }
}

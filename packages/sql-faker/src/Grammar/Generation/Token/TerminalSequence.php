<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

/**
 * Ordered terminal occurrences; rewriting preserves the original derivation.
 */
final class TerminalSequence
{
    /**
     * @param list<TerminalOccurrence> $terminals
     * @param list<TerminalOccurrence> $original
     * @param list<string> $rewrites
     * @param list<ProductionOccurrence> $productions
     */
    public function __construct(
        public readonly array $terminals,
        public readonly array $original = [],
        public readonly array $rewrites = [],
        public readonly array $productions = [],
    ) {
    }

    /**
     * @param list<string> $names
     */
    public static function fromNames(array $names): self
    {
        $terminals = [];
        foreach ($names as $index => $name) {
            $terminals[] = new TerminalOccurrence($name, $index);
        }
        return new self($terminals, $terminals);
    }

    /**
     * Reads the current terminal name, returning null outside the sequence.
     */
    public function nameAt(int $index): ?string
    {
        return $this->terminals[$index]->name ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (TerminalOccurrence $terminal): string => $terminal->name, $this->terminals);
    }

    /**
     * @param list<TerminalOccurrence> $replacement
     */
    public function replace(int $offset, int $length, array $replacement, string $rule): self
    {
        $terminals = $this->terminals;
        array_splice($terminals, $offset, $length, $replacement);
        return new self($terminals, $this->original, [...$this->rewrites, $rule], $this->productions);
    }

    /**
     * Locates the current output of a selected production, including nested children.
     * @return array{int, int}|null Inclusive start and exclusive end, or no emitted terminal
     */
    public function range(int $production): ?array
    {
        $start = null;
        $end = 0;
        foreach ($this->terminals as $index => $terminal) {
            if (in_array($production, $terminal->ancestors, true)) {
                $start ??= $index;
                $end = $index + 1;
            }
        }
        return $start === null ? null : [$start, $end];
    }

    /**
     * Lists original occurrences from the innermost/rightmost derivations first.
     * @return list<int>
     */
    public function occurrences(string $rule): array
    {
        $ids = [];
        foreach ($this->productions as $production) {
            if ($production->rule === $rule) {
                $ids[] = $production->id;
            }
        }
        return array_reverse($ids);
    }

    /**
     * Finds a direct child production, preserving empty children that have no terminal range.
     */
    public function child(int $parent, string $rule): ?ProductionOccurrence
    {
        foreach ($this->productions as $production) {
            if ($production->parent === $parent && $production->rule === $rule) {
                return $production;
            }
        }
        return null;
    }

    /**
     * Creates a new occurrence with an identity outside the original nonnegative derivation IDs.
     */
    public function inserted(string $name, TerminalOccurrence $anchor, string $rewrite, int $offset = 0): TerminalOccurrence
    {
        $lowest = 0;
        foreach ($this->terminals as $terminal) {
            $lowest = min($lowest, $terminal->id);
        }
        return new TerminalOccurrence($name, $lowest - 1 - $offset, $anchor->ancestors, $anchor->rules, $rewrite);
    }

    /**
     * Adds output to a specific production, including originally empty productions.
     */
    public function insertedFor(string $name, int $production, string $rewrite, int $offset = 0): TerminalOccurrence
    {
        $ancestors = [];
        $rules = [];
        $byId = [];
        foreach ($this->productions as $entry) {
            $byId[$entry->id] = $entry;
        }
        $current = $byId[$production] ?? null;
        while ($current !== null) {
            array_unshift($ancestors, $current->id);
            array_unshift($rules, $current->rule);
            $current = $current->parent === null ? null : ($byId[$current->parent] ?? null);
        }
        return $this->inserted($name, new TerminalOccurrence($name, $production, $ancestors, $rules), $rewrite, $offset);
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Seed;

use LogicException;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;

/**
 * Steers a leftmost derivation to one production, then finishes every remaining symbol as cheaply as possible.
 *
 * @visibility namespace
 */
final class ProductionGuide
{
    /**
     * @var list<array{string, bool}> Pending rules in leftmost order, each with whether the target lies below it
     */
    private array $pending;

    private bool $reached = false;

    /**
     * @var list<Production>
     */
    private array $productions = [];

    /**
     * @var array<string, int>
     */
    private readonly array $distances;

    /**
     * Starts at the root with the target rule's distances precomputed.
     */
    public function __construct(private readonly ProductionGraph $graph, string $root, private readonly string $rule, private readonly Production $target)
    {
        $this->pending = [[$root, true]];
        $this->distances = $graph->distances($rule);
    }

    /**
     * Chooses the next expansion for the leftmost pending rule and queues the rules the choice leaves pending.
     *
     * @param non-empty-list<Production> $candidates
     * @throws LogicException When the derivation asks for a choice after every pending rule was expanded
     */
    public function choose(int $count, array $candidates): int
    {
        [$name, $onPath] = array_shift($this->pending) ?? throw new LogicException('The derivation expanded a rule the guide does not have pending.');
        [$index, $descent] = ($onPath ? $this->toward($name, $candidates) : null) ?? [$this->graph->cheapest($candidates), null];
        $chosen = $candidates[$index];
        $this->productions[] = $chosen;
        $pending = [];
        foreach ($chosen->symbols as $offset => $symbol) {
            if ($symbol instanceof NonTerminal) {
                $pending[] = [$symbol->value, $offset === $descent];
            }
        }
        $this->pending = [...$pending, ...$this->pending];
        return $index;
    }

    /**
     * Takes the target when it is offered, or else the candidate holding the symbol nearest to the target rule.
     *
     * @param non-empty-list<Production> $candidates
     * @return array{int, int|null}|null The candidate and the offset of the symbol to descend into, or null when none leads on
     */
    public function toward(string $name, array $candidates): ?array
    {
        $offered = array_search($this->target, $candidates, true);
        if ($name === $this->rule && $offered !== false) {
            $this->reached = true;
            return [$offered, null];
        }
        $best = null;
        $nearest = PHP_INT_MAX;
        foreach ($candidates as $index => $candidate) {
            foreach ($candidate->symbols as $offset => $symbol) {
                $distance = $symbol instanceof NonTerminal ? $this->distances[$symbol->value] ?? PHP_INT_MAX : PHP_INT_MAX;
                if ($distance < $nearest) {
                    $nearest = $distance;
                    $best = [$index, $offset];
                }
            }
        }
        return $best;
    }

    /**
     * Reports whether the target production was selected.
     */
    public function reached(): bool
    {
        return $this->reached;
    }

    /**
     * Lists the productions chosen so far, in derivation order.
     *
     * @return list<Production>
     */
    public function productions(): array
    {
        return $this->productions;
    }
}

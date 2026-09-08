<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Production;

/**
 * Tracks occurrence identity alongside the sentential form without selecting productions.
 */
final class DerivationTrace
{
    /**
     * @var list<TerminalOccurrence>
     */
    private array $form;

    /**
     * @var list<ProductionOccurrence>
     */
    private array $productions = [];

    private int $next = 1;

    /**
     * Starts one derivation at a grammar entry point.
     */
    public function __construct(string $root)
    {
        $this->form = [new TerminalOccurrence($root, 0)];
    }

    /**
     * Replaces one non-terminal occurrence with its selected production's children.
     */
    public function expand(int $index, Production $production, int $ordinal): void
    {
        $node = $this->form[$index];
        $parent = $node->ancestors === [] ? null : $node->ancestors[array_key_last($node->ancestors)];
        $this->productions[] = new ProductionOccurrence($node->id, $parent, $node->name, $ordinal);
        $children = [];
        foreach ($production->symbols as $symbol) {
            $children[] = new TerminalOccurrence(
                $symbol->value(),
                $this->next++,
                [...$node->ancestors, $node->id],
                [...$node->rules, $node->name],
            );
        }
        array_splice($this->form, $index, 1, $children);
    }

    /**
     * Exposes the final leaves and every original production choice.
     */
    public function terminals(): TerminalSequence
    {
        return new TerminalSequence($this->form, $this->form, [], $this->productions);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Writer;

/**
 * Navigates the symbols of a form to the child values they stand for.
 *
 * A form lists every symbol of its grammar alternative, fixed words
 * included, while a value keeps children only for the symbols that are
 * rules. Relation rules name symbols, so the child at a symbol has to be
 * found by counting the rule symbols before it.
 *
 * @visibility SqlSemantics
 */
final class Forms
{
    /**
     * Navigates forms of the vocabulary.
     */
    public function __construct(private readonly Vocabulary $vocabulary)
    {
    }

    /**
     * Finds the first position of a symbol in the form that is not yet taken.
     *
     * @param list<string> $symbols
     * @param array<int, true> $taken
     *
     * @throws LogicException When the form has no free position for the symbol
     */
    public function position(array $symbols, string $symbol, array $taken = []): int
    {
        foreach ($symbols as $position => $candidate) {
            if ($candidate === $symbol && !isset($taken[$position])) {
                return $position;
            }
        }

        throw new LogicException('The relation rules name a symbol the form does not have: ' . $symbol);
    }

    /**
     * Answers the child value at a symbol position, counting only the symbols that are rules.
     *
     * @param list<string> $symbols
     * @param list<Element> $children
     *
     * @throws LogicException When the position is not a rule symbol of the form
     */
    public function child(array $symbols, array $children, int $position): Element
    {
        $index = 0;
        foreach ($symbols as $at => $symbol) {
            if ($at === $position) {
                return $children[$index] ?? throw new LogicException('The form has no value at symbol position ' . $position);
            }
            if ($this->vocabulary->isRule($symbol)) {
                $index++;
            }
        }

        throw new LogicException('The form has no symbol position ' . $position);
    }

    /**
     * Reports whether a conditional symbol of the form is present: a fixed word among the symbols, or a rule whose value writes something.
     *
     * @param list<string> $symbols
     * @param list<Element> $children
     *
     * @throws LogicException When the symbol is a rule the form has no value for
     */
    public function conditional(array $symbols, array $children, string $symbol): bool
    {
        if (!$this->vocabulary->isRule($symbol)) {
            return in_array($symbol, $symbols, true);
        }
        $position = array_search($symbol, $symbols, true);

        return $position !== false && Writer::render($this->child($symbols, $children, $position)) !== '';
    }

    /**
     * Unfolds a left-recursive list form into its items, in writing order.
     *
     * @param list<string> $symbols
     * @return list<Element>
     */
    public function unfold(Element $list, string $rule, array $symbols): array
    {
        $shape = $this->vocabulary->shape($list);
        if ($shape === null || $shape['rule'] !== $rule || $shape['symbols'] !== $symbols) {
            return [$list];
        }
        $children = $list->children();

        return [...$this->unfold($children[0], $rule, $symbols), $children[count($children) - 1]];
    }
}

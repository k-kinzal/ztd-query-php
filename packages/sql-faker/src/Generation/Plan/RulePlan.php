<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan;

use InvalidArgumentException;

/**
 * Conditions on a grammar subtree; nested conditions use grammar roles rather than global occurrence numbers.
 *
 * @visibility public
 * @example Keep every matching production available
 *     $pattern = \SqlFaker\Generation\Plan\ProductionPattern::containing('SELECT');
 *     $plan = \SqlFaker\Generation\Plan\RulePlan::any()->allowing($pattern);
 *     $plan->pattern?->matches(['SELECT', 'expr']) // => true
 */
final class RulePlan
{
    /**
     * @param array<string, self> $rules Descendant scopes, with nearer scopes taking precedence
     * @param array<string, LexemeConstraint> $lexemes Token domains inside this scope
     * @param array<string, array<int, self>> $children Conditions on direct children, counted separately per production
     * @param non-empty-list<self>|null $items Ordered items of a directly recursive grammar list
     */
    public function __construct(
        public readonly ?ProductionPattern $pattern = null,
        public readonly array $rules = [],
        public readonly array $lexemes = [],
        public readonly ?array $items = null,
        public readonly array $children = [],
    ) {
    }

    /**
     * Leaves every alternative available until conditions are added.
     */
    public static function any(): self
    {
        return new self();
    }

    /**
     * Adds a condition on the root production without specifying descendants.
     */
    public function allowing(ProductionPattern $pattern): self
    {
        return new self($this->pattern === null ? $pattern : ProductionPattern::allOf($this->pattern, $pattern), $this->rules, $this->lexemes, $this->items, $this->children);
    }

    /**
     * Applies a plan whenever the named descendant rule is reached in this scope.
     * @throws InvalidArgumentException When the rule name is empty or its conditions conflict
     */
    public function withRule(string $rule, self $plan): self
    {
        if ($rule === '') {
            throw new InvalidArgumentException('A planned rule name must not be empty.');
        }
        $rules = $this->rules;
        $rules[$rule] = isset($rules[$rule]) ? $rules[$rule]->merge($plan) : $plan;
        return new self($this->pattern, $rules, $this->lexemes, $this->items, $this->children);
    }

    /**
     * Constrains a direct child, such as an operand, without counting unrelated subtrees.
     * @throws InvalidArgumentException When the child selector or combined conditions are invalid
     */
    public function withChild(string $rule, int $index, self $plan): self
    {
        if ($rule === '' || $index < 0) {
            throw new InvalidArgumentException('A child needs a rule name and a non-negative index.');
        }
        $children = $this->children;
        $children[$rule][$index] = isset($children[$rule][$index]) ? $children[$rule][$index]->merge($plan) : $plan;
        return new self($this->pattern, $this->rules, $this->lexemes, $this->items, $children);
    }

    /**
     * Constrains every matching terminal in this scope, independently of sibling scopes.
     * @throws InvalidArgumentException When the terminal is empty or domains are disjoint
     */
    public function withLexeme(string $terminal, LexemeConstraint $constraint): self
    {
        if ($terminal === '') {
            throw new InvalidArgumentException('A planned terminal name must not be empty.');
        }
        $lexemes = $this->lexemes;
        $lexemes[$terminal] = isset($lexemes[$terminal]) ? $lexemes[$terminal]->intersect($constraint) : $constraint;
        return new self($this->pattern, $this->rules, $lexemes, $this->items, $this->children);
    }

    /**
     * Specifies list items in output order while retaining compatible list productions.
     * Each item constrains the non-recursive portion of one production of the list.
     * @throws InvalidArgumentException When two list conditions disagree
     */
    public function withItems(self $first, self ...$others): self
    {
        return $this->merge(new self(items: [$first, ...array_values($others)]));
    }

    /**
     * Combines conditions at the same scope by intersection, never by silently replacing them.
     * @throws InvalidArgumentException When lexical domains or list lengths conflict
     */
    public function merge(self $other): self
    {
        $result = $other->pattern === null ? $this : $this->allowing($other->pattern);
        foreach ($other->rules as $rule => $plan) {
            $result = $result->withRule($rule, $plan);
        }
        foreach ($other->lexemes as $terminal => $constraint) {
            $result = $result->withLexeme($terminal, $constraint);
        }
        foreach ($other->children as $rule => $children) {
            foreach ($children as $index => $child) {
                $result = $result->withChild($rule, $index, $child);
            }
        }
        $items = $result->items ?? $other->items;
        if ($result->items !== null && $other->items !== null) {
            if (count($result->items) !== count($other->items)) {
                throw new InvalidArgumentException('List constraints require different item counts.');
            }
            $items = [];
            foreach ($result->items as $index => $item) {
                $items[] = $item->merge($other->items[$index]);
            }
        }
        return new self($result->pattern, $result->rules, $result->lexemes, $items, $result->children);
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan\Compilation;

use InvalidArgumentException;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\RulePlan;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Symbol;

/**
 * Specializes grammar subtrees before making choices; every alternative retains its source production.
 */
final class GrammarCompiler
{
    /** @var array<string, string> */
    private array $names = [];
    /** @var array<string, string> */
    private array $aliases = [];
    /** @var array<string, ProductionRule> */
    private array $rules = [];
    /** @var array<string, array<int, array<string, LexemeConstraint>>> */
    private array $lexemes = [];

    /**
     * Keeps the dialect grammar immutable across different plans.
     */
    public function __construct(private readonly Grammar $grammar)
    {
    }

    /**
     * @param array<string, RulePlan> $plans
     * @throws InvalidArgumentException When a declaration names an unknown rule
     * @throws GenerationException When no finite derivation satisfies the conditions
     */
    public function compile(string $root, array $plans): PreparedGrammar
    {
        $this->validate($plans);
        $entry = $this->rule($root, new Scope($plans));
        $grammar = new Grammar($entry, $this->rules, $this->aliases);
        if ((new TerminationAnalyzer($grammar))->getMinLength($entry) === PHP_INT_MAX) {
            throw GenerationException::noAlternativeMatchingPlan($root);
        }
        return new PreparedGrammar($grammar, $this->lexemes);
    }

    /**
     * Checks all declarations, including conditionally visited branches.
     * @param array<string, RulePlan> $plans
     * @throws InvalidArgumentException When a planned rule is absent from this grammar
     */
    public function validate(array $plans): void
    {
        foreach ($plans as $name => $plan) {
            if (!isset($this->grammar->ruleMap[$name])) {
                throw new InvalidArgumentException('Unknown planned grammar rule: ' . $name);
            }
            $this->validate($plan->rules);
            foreach ($plan->children as $childName => $children) {
                foreach ($children as $child) {
                    $this->validate([$childName => $child]);
                }
            }
            foreach ($plan->items ?? [] as $item) {
                $this->validate([$name => $item]);
            }
        }
    }

    /**
     * Memoizes recursive scopes before visiting their descendants.
     * @param non-empty-list<RulePlan>|null $items Remaining items of a planned list
     */
    public function rule(string $name, Scope $scope, ?array $items = null): string
    {
        $plan = $scope->rules[$name] ?? RulePlan::any();
        $scope = $scope->enter($plan);
        $items ??= $plan->items;
        $key = $name . ':' . serialize([$scope, $plan->pattern, $plan->children, $items]);
        if (isset($this->names[$key])) {
            return $this->names[$key];
        }
        $alias = '@plan' . count($this->names);
        $this->names[$key] = $alias;
        $this->aliases[$alias] = $name;
        $this->rules[$alias] = new ProductionRule($alias, []);
        $alternatives = [];
        foreach ($this->grammar->ruleMap[$name]->alternatives ?? [] as $ordinal => $production) {
            $symbols = array_map(static fn (Symbol $symbol): string => $symbol->value(), $production->symbols);
            if ($plan->pattern !== null && !$plan->pattern->matches($symbols, $ordinal)) {
                continue;
            }
            $item = $this->item($name, $production, $items);
            if ($item === false || ($item?->pattern !== null && !$item->pattern->matches($symbols, $ordinal))) {
                continue;
            }
            $children = $item === null ? $plan->children : $plan->merge($item)->children;
            if (!$this->hasChildren($production, $children)) {
                continue;
            }
            $inner = $item === null ? $scope : $scope->enter($item);
            $expanded = $this->symbols($name, $production, $scope, $inner, $items, $children);
            $this->lexemes[$alias][count($alternatives)] = $inner->lexemes;
            $alternatives[] = new Production($expanded, $ordinal, $production->origin);
        }
        $this->rules[$alias] = new ProductionRule($alias, $alternatives);
        return $alias;
    }

    /**
     * Lists may recurse at either end; item order always means output order.
     * @param non-empty-list<RulePlan>|null $items
     */
    public function item(string $name, Production $production, ?array $items): RulePlan|false|null
    {
        if ($items === null) {
            return null;
        }
        $positions = [];
        foreach ($production->symbols as $index => $symbol) {
            if ($symbol instanceof NonTerminal && $symbol->value === $name) {
                $positions[] = $index;
            }
        }
        if (count($items) === 1) {
            return $positions === [] && $production->symbols !== [] ? $items[0] : false;
        }
        if (count($positions) !== 1 || count($production->symbols) < 2) {
            return false;
        }
        return match ($positions[0]) {
            0 => $items[count($items) - 1],
            count($production->symbols) - 1 => $items[0],
            default => false,
        };
    }

    /**
     * Required child roles prune incompatible alternatives before generation.
     * @param array<string, array<int, RulePlan>> $children
     */
    public function hasChildren(Production $production, array $children): bool
    {
        $counts = [];
        foreach ($production->symbols as $symbol) {
            if ($symbol instanceof NonTerminal) {
                $counts[$symbol->value] = ($counts[$symbol->value] ?? 0) + 1;
            }
        }
        foreach ($children as $name => $plans) {
            foreach ($plans as $index => $plan) {
                if ($index >= ($counts[$name] ?? 0)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Compiles list tails in the list scope and each item's fields in its own scope.
     * @param non-empty-list<RulePlan>|null $items
     * @param array<string, array<int, RulePlan>> $children
     * @return list<Symbol>
     * @throws InvalidArgumentException When a recursive list tail has no items
     */
    public function symbols(string $name, Production $production, Scope $scope, Scope $inner, ?array $items, array $children): array
    {
        $symbols = [];
        $occurrences = [];
        foreach ($production->symbols as $index => $symbol) {
            if (!$symbol instanceof NonTerminal) {
                $symbols[] = $symbol;
                continue;
            }
            $occurrence = $occurrences[$symbol->value] ?? 0;
            $occurrences[$symbol->value] = $occurrence + 1;
            $child = $children[$symbol->value][$occurrence] ?? null;
            if ($items !== null && $symbol->value === $name && count($items) > 1) {
                $remaining = $index === 0 ? array_slice($items, 0, -1) : array_slice($items, 1);
                if ($remaining === []) {
                    throw new InvalidArgumentException('A recursive list tail must retain an item.');
                }
                $tailScope = $child === null ? $scope : new Scope(array_replace($scope->rules, [$name => $child]), $scope->lexemes);
                $symbols[] = new NonTerminal($this->rule($name, $tailScope, $remaining));
            } else {
                $childScope = $child === null ? $inner : new Scope(array_replace($inner->rules, [$symbol->value => $child]), $inner->lexemes);
                $symbols[] = new NonTerminal($this->rule($symbol->value, $childScope));
            }
        }
        return $symbols;
    }
}

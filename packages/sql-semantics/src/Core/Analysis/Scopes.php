<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Core\Policy\NameRules;
use SqlSemantics\Core\Policy\RelationRules;
use SqlSemantics\Core\Policy\WithVisibility;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Writer;

/**
 * Walks a statement with the common table expressions visible at each value.
 *
 * A form that writes a WITH clause makes the clause's expressions visible
 * in every other value of the form. Inside the clause, the body of each
 * expression sees the expressions of the clause the database allows, which
 * differ between a plain and a recursive clause; a name it cannot see is
 * resolved outside the clause. A value at a target position of the relation
 * rules, a table a DML statement writes to where the database never reads
 * it as an expression, sees no expression at all.
 *
 * @visibility SqlSemantics
 */
final class Scopes
{
    /**
     * Reads WITH clauses of a vocabulary under its relation rules.
     */
    public function __construct(private readonly Vocabulary $vocabulary, private readonly RelationRules $rules, private readonly NameRules $names, private readonly Forms $forms)
    {
    }

    /**
     * Yields a value and then every value below it, each before its own children, with the scope visible at it.
     *
     * @return iterable<int, array{Element, Scope}>
     */
    public function walk(Element $value, Scope $scope = new Scope()): iterable
    {
        yield [$value, $scope];
        $with = $this->withClause($value);
        $scopes = $this->children($value, $scope);
        foreach ($value->children() as $index => $child) {
            if ($index === $with) {
                yield from $this->clause($child, $scope, $this->expressions($child, $scope, $this->visibility($value, $child)));
            } else {
                yield from $this->walk($child, $scopes[$index]);
            }
        }
    }

    /**
     * Answers the scope visible at each child of a value, by child index, given the scope visible at the value.
     *
     * The WITH clause of the form keeps the scope of the value, whose
     * expressions are visible at every other child except a target.
     *
     * @return list<Scope>
     */
    public function children(Element $value, Scope $scope): array
    {
        $children = $value->children();
        $positions = $this->positions($this->vocabulary->shape($value)['symbols'] ?? []);
        $with = $this->withClause($value);
        $inner = $with === null ? $scope : $scope->with(array_column($this->definitions($children[$with]), 1));
        $scopes = [];
        foreach (array_keys($children) as $index) {
            $scopes[] = match (true) {
                $index === $with => $scope,
                in_array($positions[$index] ?? '', $this->rules->targets, true) => new Scope(),
                default => $inner,
            };
        }

        return $scopes;
    }

    /**
     * Answers the child index of the WITH clause a form writes, or null when it writes none.
     */
    public function withClause(Element $value): ?int
    {
        foreach ($this->positions($this->vocabulary->shape($value)['symbols'] ?? []) as $index => $symbol) {
            if (in_array($symbol, $this->rules->withClauses, true)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers which expressions of a WITH clause their bodies can name: the recursive rule when the clause or its form writes the recursive word.
     */
    public function visibility(Element $form, Element $clause): WithVisibility
    {
        $symbols = [...$this->vocabulary->shape($form)['symbols'] ?? [], ...$this->vocabulary->shape($clause)['symbols'] ?? []];

        return in_array($this->rules->recursive, $symbols, true) ? $this->rules->recursiveVisibility : $this->rules->visibility;
    }

    /**
     * Walks a WITH clause: each of its expressions with the scope its body sees, everything else with the outer scope.
     *
     * @param array<int, Scope> $expressions The scope of each expression of the clause, by object id
     *
     * @return iterable<int, array{Element, Scope}>
     */
    public function clause(Element $value, Scope $outer, array $expressions): iterable
    {
        $scope = $expressions[spl_object_id($value)] ?? null;
        if ($scope !== null) {
            yield from $this->walk($value, $scope);

            return;
        }
        yield [$value, $outer];
        foreach ($value->children() as $child) {
            yield from $this->clause($child, $outer, $expressions);
        }
    }

    /**
     * Answers the scope the body of each expression of a WITH clause sees, by object id: the outer scope and the expressions of the clause the visibility allows.
     *
     * @return array<int, Scope>
     */
    public function expressions(Element $clause, Scope $outer, WithVisibility $visibility): array
    {
        $definitions = $this->definitions($clause);
        $names = array_column($definitions, 1);
        $scopes = [];
        foreach ($definitions as $position => [$expression]) {
            $scopes[spl_object_id($expression)] ??= $outer->with($visibility->visible($names, $position));
        }

        return $scopes;
    }

    /**
     * Lists the expressions a WITH clause defines with their decoded names, in writing order, without reading the clauses inside their bodies.
     *
     * @return list<array{Element, string}>
     */
    public function definitions(Element $clause): array
    {
        $name = $this->definition($clause);
        if ($name !== null) {
            return [[$clause, $name]];
        }
        $definitions = [];
        foreach ($clause->children() as $child) {
            array_push($definitions, ...$this->definitions($child));
        }

        return $definitions;
    }

    /**
     * Answers the decoded name a value defines when it is a common table expression, or null.
     */
    public function definition(Element $value): ?string
    {
        $shape = $this->vocabulary->shape($value);
        if ($shape === null) {
            return null;
        }
        foreach (RelationRules::matching($this->rules->commonTableExpressions, $shape['rule'], $shape['symbols']) as $site) {
            if (isset($site['name'])) {
                return $this->names->decode(Writer::render($this->forms->child($shape['symbols'], $value->children(), $this->forms->position($shape['symbols'], $site['name']))));
            }
        }

        return null;
    }

    /**
     * Answers the grammar symbol each child of a form stands for, by child index.
     *
     * @param list<string> $symbols
     * @return array<int, string>
     */
    public function positions(array $symbols): array
    {
        $positions = [];
        foreach ($symbols as $symbol) {
            if ($this->vocabulary->isRule($symbol)) {
                $positions[] = $symbol;
            }
        }

        return $positions;
    }
}

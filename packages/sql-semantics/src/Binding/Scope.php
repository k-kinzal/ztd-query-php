<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\TableUse;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

/**
 * A relation namespace and NULL-extension facts at one evaluation stage.
 *
 * @visibility SqlSemantics
 */
final class Scope
{
    /**
     * @param list<TableUse> $relations
     * @param array<string, list<string>> $extensions
     * @param array<int|string, Expression> $merged USING columns visible without a qualifier
     * @param list<\SqlSemantics\Model\OutputColumn>|null $outputs Ordered joined row; null for a base relation scope
     */
    public function __construct(
        public readonly Identifiers $identifiers,
        public readonly array $relations = [],
        public readonly array $extensions = [],
        public readonly ?self $parent = null,
        public readonly ?QueryContext $queries = null,
        public readonly array $merged = [],
        public readonly ?array $outputs = null,
    ) {
    }

    /**
     * @param list<string> $parts
     * @throws SemanticException
     */
    public function column(array $parts, Node|Token $source): Expression
    {
        $name = $parts[count($parts) - 1];
        $qualifiers = array_slice($parts, 0, -1);
        if ($qualifiers === [] && ($merged = Query\Joining\RowNamespace::resolve($this, $name, $source)) !== null) {
            return $merged;
        }
        $matches = [];
        $origins = [];
        $versions = [];
        foreach ($this->relations as $relation) {
            if (!$this->matches($relation, $qualifiers)) {
                continue;
            }
            foreach ($relation->declaration->columns as $ordinal => $column) {
                if ($this->identifiers->equal($column->name, $name)) {
                    $matches[] = new ColumnBinding($relation->id, $relation->declaration, $column);
                    if ($relation instanceof \SqlSemantics\Model\Relation\TriggerRow) {
                        $versions[$relation->id] = $relation->version;
                    }
                    if (isset($relation->resultExpressions()[$ordinal])) {
                        $origins[] = $relation->resultExpressions()[$ordinal];
                    }
                }
            }
        }
        if ($matches === [] && $this->parent !== null && ($qualifiers === [] || array_filter($this->relations, fn (TableUse $relation): bool => $this->matches($relation, $qualifiers)) === [])) {
            return $this->parent->column($parts, $source);
        }
        if ($matches === [] && ($literal = (new LiteralBinder($this->identifiers->dialect))->fallback($source)) !== null) {
            return $literal;
        }
        if (count($matches) !== 1) {
            $this->diagnostics()->report($matches === [] ? 'unknown-column' : 'ambiguous-column', 'Cannot resolve column unambiguously: ' . implode('.', $parts), $source);
            return new \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin($this->identifiers->dialect, 'unknown'), Nullability::Unknown, []), $source, $parts);
        }
        $binding = $matches[0];
        if (isset($versions[$binding->relationId])) {
            return new \SqlSemantics\Model\Scalar\Reference\TriggerColumn(new \SqlSemantics\Model\Scalar\ExpressionFacts($binding->column->type, $binding->column->nullability), $source, $binding, $versions[$binding->relationId]);
        }
        $extensions = $this->extensions[$binding->relationId] ?? [];

        return new \SqlSemantics\Model\Scalar\Reference\ColumnReference(new \SqlSemantics\Model\Scalar\ExpressionFacts($binding->column->type, $extensions === [] ? $binding->column->nullability : Nullability::MaybeNull, $extensions), $source, $binding, $origins, [...$qualifiers, $name]);
    }

    /**
     * Shares diagnostics across nested scopes.
     */
    public function diagnostics(): Analysis\Diagnostics
    {
        return $this->queries?->tables->diagnostics ?? $this->parent?->diagnostics() ?? new Analysis\Diagnostics();
    }

    /**
     * @param list<string> $qualifiers
     */
    public function matches(TableUse $relation, array $qualifiers): bool
    {
        if ($qualifiers === []) {
            return true;
        }
        if (count($qualifiers) === 1) {
            return $this->identifiers->relationEqual($qualifiers[0], $relation->alias ?? $relation->declaration->name);
        }

        return count($qualifiers) === 2 && $relation->alias === null
            && $this->identifiers->relationEqual($qualifiers[0], $relation->declaration->schema)
            && $this->identifiers->relationEqual($qualifiers[1], $relation->declaration->name);
    }

    /**
     * @throws SemanticException
     */
    public function combine(self $right, Node $source): self
    {
        foreach ($this->relations as $leftRelation) {
            foreach ($right->relations as $rightRelation) {
                if ($this->identifiers->relationEqual($leftRelation->alias ?? $leftRelation->declaration->name, $rightRelation->alias ?? $rightRelation->declaration->name)) {
                    $this->diagnostics()->report('duplicate-relation', 'Duplicate relation name in scope.', $source);
                }
            }
        }

        return new self($this->identifiers, [...$this->relations, ...$right->relations], $this->extensions + $right->extensions, $this->parent ?? $right->parent, $this->queries ?? $right->queries, $this->merged + $right->merged, Query\Joining\RowNamespace::combine($this, $right, $source));
    }

    /**
     * Adds a join to the NULL provenance of every visible relation.
     */
    public function extend(string $joinId): self
    {
        $extensions = $this->extensions;
        foreach ($this->relations as $relation) {
            $extensions[$relation->id] = [...($extensions[$relation->id] ?? []), $joinId];
        }

        $merged = array_map(static fn (Expression $value): Expression => $value->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($value->type, Nullability::MaybeNull, [...$value->nullExtendedBy, $joinId])), $this->merged);
        return new self($this->identifiers, $this->relations, $extensions, $this->parent, $this->queries, $merged, $this->outputs === null ? null : Query\Joining\RowNamespace::extend($this->outputs, $joinId));
    }
    /**
     * @return array<int|string, Expression> Unqualified joined output columns
     */
    public function outputColumns(Node $source): array
    {
        $columns = [];
        foreach (Query\Joining\RowNamespace::read($this, $source) as $column) {
            if ($column->name !== null) {
                $columns[$column->name] = $column->expression;
            }
        }
        return $columns;
    }


    /**
     * Resolves a USING/NATURAL join's shared column under this dialect's name rules.
     */
    public function mergedColumn(string $name): ?Expression
    {
        foreach ($this->merged as $label => $expression) {
            if ($this->identifiers->equal((string) $label, $name)) {
                return $expression;
            }
        }
        return null;
    }
}
